<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Fulfillment\Domain\Models\FulfillmentItem;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateFulfillment
{
    public function __construct(private readonly FulfillmentPaymentEligibility $paymentEligibility) {}

    /**
     * @param  list<array{order_item_id: string, inventory_reservation_id: string, quantity: int}>  $allocations
     */
    public function handle(
        Tenant $tenant,
        Order $order,
        InventoryLocation $location,
        array $allocations,
        ?User $actor = null,
    ): Fulfillment {
        if ($allocations === []) {
            throw ValidationException::withMessages(['items' => 'A fulfillment requires at least one item.']);
        }

        return DB::transaction(function () use ($tenant, $order, $location, $allocations, $actor): Fulfillment {
            $lockedOrder = Order::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if ($lockedOrder->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages(['order' => 'Only a confirmed order can be fulfilled.']);
            }
            $eligibility = $this->paymentEligibility->evaluate($lockedOrder);
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages(['payment' => $eligibility['reason']]);
            }
            $lockedLocation = $tenant->inventoryLocations()->lockForUpdate()->findOrFail($location->getKey());
            if ($lockedLocation->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['location' => 'Fulfillment requires an active inventory location.']);
            }

            $orderItemIds = collect($allocations)->pluck('order_item_id')->unique()->sort()->values();
            $reservationIds = collect($allocations)->pluck('inventory_reservation_id')->unique()->sort()->values();
            if ($reservationIds->count() !== collect($allocations)->pluck('inventory_reservation_id')->count()) {
                throw ValidationException::withMessages(['items' => 'Each reservation may appear only once per fulfillment.']);
            }
            $orderItems = OrderItem::query()->where('tenant_id', $tenant->getKey())
                ->where('order_id', $lockedOrder->getKey())->whereIn('id', $orderItemIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $reservations = InventoryReservation::query()->where('tenant_id', $tenant->getKey())
                ->whereIn('id', $reservationIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($orderItems->count() !== $orderItemIds->count() || $reservations->count() !== $reservationIds->count()) {
                throw ValidationException::withMessages(['items' => 'A fulfillment allocation is outside this order or tenant.']);
            }

            foreach ($allocations as $allocation) {
                $this->validateAllocation($lockedOrder, $lockedLocation, $orderItems[$allocation['order_item_id']], $reservations[$allocation['inventory_reservation_id']], $allocation['quantity']);
                $allocatedToItem = (int) FulfillmentItem::query()
                    ->join('fulfillments', 'fulfillments.id', '=', 'fulfillment_items.fulfillment_id')
                    ->where('fulfillment_items.tenant_id', $tenant->getKey())
                    ->where('fulfillment_items.order_item_id', $allocation['order_item_id'])
                    ->where('fulfillments.status', '!=', FulfillmentStatus::Cancelled)->sum('fulfillment_items.quantity');
                $allocatedFromReservation = (int) FulfillmentItem::query()
                    ->join('fulfillments', 'fulfillments.id', '=', 'fulfillment_items.fulfillment_id')
                    ->where('fulfillment_items.tenant_id', $tenant->getKey())
                    ->where('fulfillment_items.inventory_reservation_id', $allocation['inventory_reservation_id'])
                    ->where('fulfillments.status', '!=', FulfillmentStatus::Cancelled)->sum('fulfillment_items.quantity');
                $requestedForItem = (int) collect($allocations)
                    ->where('order_item_id', $allocation['order_item_id'])->sum('quantity');
                if ($allocatedToItem + $requestedForItem > $orderItems[$allocation['order_item_id']]->quantity
                    || $allocatedFromReservation + $allocation['quantity'] > $reservations[$allocation['inventory_reservation_id']]->quantity) {
                    throw ValidationException::withMessages(['quantity' => 'The quantity exceeds the unallocated order or reservation quantity.']);
                }
            }

            $fulfillment = Fulfillment::query()->create([
                'tenant_id' => $tenant->getKey(), 'store_id' => $lockedOrder->store_id,
                'order_id' => $lockedOrder->getKey(), 'inventory_location_id' => $lockedLocation->getKey(),
                'status' => FulfillmentStatus::Pending, 'created_by_user_id' => $actor?->getKey(),
            ]);
            foreach ($allocations as $allocation) {
                $fulfillment->items()->create([
                    'tenant_id' => $tenant->getKey(), 'store_id' => $lockedOrder->store_id,
                    'order_id' => $lockedOrder->getKey(), 'order_item_id' => $allocation['order_item_id'],
                    'inventory_reservation_id' => $allocation['inventory_reservation_id'],
                    'inventory_location_id' => $lockedLocation->getKey(),
                    'product_variant_id' => $reservations[$allocation['inventory_reservation_id']]->product_variant_id,
                    'quantity' => $allocation['quantity'],
                ]);
            }
            $fulfillment->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => null, 'to_status' => FulfillmentStatus::Pending,
                'actor_user_id' => $actor?->getKey(),
            ]);

            return $fulfillment->load(['items', 'history']);
        });
    }

    private function validateAllocation(
        Order $order,
        InventoryLocation $location,
        OrderItem $item,
        InventoryReservation $reservation,
        int $quantity,
    ): void {
        if ($quantity <= 0 || $quantity > 9999) {
            throw ValidationException::withMessages(['quantity' => 'A fulfillment quantity must be between 1 and 9999.']);
        }
        if ($reservation->status !== InventoryReservationStatus::Active
            || ($reservation->expires_at !== null && $reservation->expires_at->isPast())) {
            throw ValidationException::withMessages(['reservation' => 'The inventory reservation is not active.']);
        }
        if ($reservation->inventory_location_id !== $location->getKey()
            || $reservation->product_variant_id !== $item->product_variant_id
            || $reservation->reference_type !== 'CHECKOUT_LINE'
            || $reservation->reference_id !== $item->checkout_line_id
            || $item->order_id !== $order->getKey()) {
            throw ValidationException::withMessages(['reservation' => 'The reservation does not match the order item and location.']);
        }
    }
}
