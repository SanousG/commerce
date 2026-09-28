<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Inventory\Application\InventoryLevelLocker;
use App\Modules\Inventory\Application\InventoryWriter;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteFulfillment
{
    public function __construct(
        private readonly InventoryLevelLocker $levels,
        private readonly InventoryWriter $writer,
        private readonly FulfillmentPaymentEligibility $paymentEligibility,
        private readonly OrderFulfillmentStatusUpdater $statusUpdater,
    ) {}

    public function handle(Tenant $tenant, Fulfillment $fulfillment, ?User $actor = null): Fulfillment
    {
        return DB::transaction(function () use ($tenant, $fulfillment, $actor): Fulfillment {
            $orderId = Fulfillment::query()->where('tenant_id', $tenant->getKey())
                ->whereKey($fulfillment->getKey())->valueOrFail('order_id');
            $order = Order::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($orderId);
            $locked = Fulfillment::query()->where('tenant_id', $tenant->getKey())
                ->where('order_id', $order->getKey())->lockForUpdate()->findOrFail($fulfillment->getKey());
            if ($locked->status === FulfillmentStatus::Fulfilled) {
                return $locked->load(['items', 'history']);
            }
            if ($locked->status !== FulfillmentStatus::Processing || $order->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages(['fulfillment' => 'Only a processing fulfillment for a confirmed order can be completed.']);
            }
            $eligibility = $this->paymentEligibility->evaluate($order);
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages(['payment' => $eligibility['reason']]);
            }

            $items = $locked->items()->orderBy('id')->get();
            $reservationIds = $items->pluck('inventory_reservation_id')->unique()->sort()->values();
            $reservations = InventoryReservation::query()->where('tenant_id', $tenant->getKey())
                ->whereIn('id', $reservationIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($items->isEmpty() || $reservations->count() !== $reservationIds->count()) {
                throw ValidationException::withMessages(['fulfillment' => 'The fulfillment inventory allocation is incomplete.']);
            }
            $levels = $this->levels->lockMany($tenant, $reservations->map(
                fn (InventoryReservation $reservation): array => [$reservation->inventory_location_id, $reservation->product_variant_id],
            )->all())->keyBy(fn (InventoryLevel $level): string => $level->inventory_location_id.':'.$level->product_variant_id);

            foreach ($items as $item) {
                $reservation = $reservations[$item->inventory_reservation_id];
                $remaining = $reservation->quantity - $reservation->committed_quantity;
                $alreadyFulfilled = (int) $order->fulfillments()
                    ->join('fulfillment_items', 'fulfillment_items.fulfillment_id', '=', 'fulfillments.id')
                    ->where('fulfillments.status', FulfillmentStatus::Fulfilled)
                    ->where('fulfillment_items.order_item_id', $item->order_item_id)
                    ->sum('fulfillment_items.quantity');
                $fulfillmentQuantity = (int) $items->where('order_item_id', $item->order_item_id)->sum('quantity');
                $orderQuantity = (int) $order->items()->whereKey($item->order_item_id)->valueOrFail('quantity');
                if ($reservation->status !== InventoryReservationStatus::Active
                    || $reservation->inventory_location_id !== $locked->inventory_location_id
                    || $reservation->product_variant_id !== $item->product_variant_id
                    || $alreadyFulfilled + $fulfillmentQuantity > $orderQuantity
                    || $remaining < $item->quantity) {
                    throw ValidationException::withMessages(['reservation' => 'A fulfillment allocation is no longer valid or available.']);
                }
                $level = $levels[$reservation->inventory_location_id.':'.$reservation->product_variant_id];
                $this->writer->change(
                    $level,
                    -$item->quantity,
                    -$item->quantity,
                    InventoryMovementType::ReservationCommit,
                    $actor,
                    referenceType: 'FULFILLMENT_ITEM',
                    referenceId: $item->getKey(),
                    correlationId: $locked->getKey(),
                );
                $committed = $reservation->committed_quantity + $item->quantity;
                $reservation->update($committed === $reservation->quantity
                    ? ['committed_quantity' => $committed, 'status' => InventoryReservationStatus::Committed, 'committed_at' => now()]
                    : ['committed_quantity' => $committed]);
            }

            $locked->update(['status' => FulfillmentStatus::Fulfilled, 'fulfilled_at' => now()]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => FulfillmentStatus::Processing,
                'to_status' => FulfillmentStatus::Fulfilled, 'actor_user_id' => $actor?->getKey(),
            ]);
            $this->statusUpdater->update($order);

            return $locked->fresh(['items', 'history']);
        });
    }
}
