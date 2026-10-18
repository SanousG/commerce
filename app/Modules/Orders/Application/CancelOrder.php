<?php

namespace App\Modules\Orders\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Application\OrderHasSuccessfulCaptures;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    public function __construct(
        private readonly OrderInventoryReservations $reservations,
        private readonly OrderHasSuccessfulCaptures $captures,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(Tenant $tenant, Order $order, string $reason, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($tenant, $order, $reason, $actor): Order {
            $locked = Order::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if ($locked->status === OrderStatus::Cancelled) {
                return $locked;
            }
            if ($this->captures->check($locked)) {
                throw ValidationException::withMessages([
                    'order' => 'PAYMENT_REFUND_REQUIRED: captured payments must be refunded before cancellation.',
                ]);
            }
            if (Fulfillment::query()->where('tenant_id', $tenant->getKey())->where('order_id', $locked->getKey())
                ->where('status', FulfillmentStatus::Fulfilled)->exists()) {
                throw ValidationException::withMessages([
                    'order' => 'FULFILLMENT_RETURN_REQUIRED: fulfilled items must be returned before cancellation.',
                ]);
            }
            if ($locked->status !== OrderStatus::Confirmed) {
                throw ValidationException::withMessages(['order' => 'This order cannot be cancelled.']);
            }

            $openFulfillments = Fulfillment::query()->where('tenant_id', $tenant->getKey())
                ->where('order_id', $locked->getKey())
                ->whereIn('status', [FulfillmentStatus::Pending, FulfillmentStatus::Processing])
                ->orderBy('id')->lockForUpdate()->get();
            $openShipments = Shipment::query()->where('tenant_id', $tenant->getKey())
                ->where('order_id', $locked->getKey())
                ->whereIn('status', [ShipmentStatus::Pending, ShipmentStatus::Ready])
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($openShipments as $shipment) {
                $from = $shipment->status;
                $shipment->update(['status' => ShipmentStatus::Cancelled, 'cancelled_at' => now()]);
                $shipment->history()->create([
                    'tenant_id' => $tenant->getKey(), 'from_status' => $from, 'to_status' => ShipmentStatus::Cancelled,
                    'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
                ]);
            }
            foreach ($openFulfillments as $fulfillment) {
                $from = $fulfillment->status;
                $fulfillment->update([
                    'status' => FulfillmentStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
                $fulfillment->history()->create([
                    'tenant_id' => $tenant->getKey(), 'from_status' => $from, 'to_status' => FulfillmentStatus::Cancelled,
                    'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
                ]);
            }
            $this->reservations->release($tenant, $locked);
            $locked->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => OrderStatus::Confirmed,
                'to_status' => OrderStatus::Cancelled, 'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
            ]);
            $this->audit->record(
                $tenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::OrderCancelled,
                AuditSubjectType::Order,
                (string) $locked->getKey(),
                ['status' => OrderStatus::Cancelled->value],
                $locked->store,
                $actor,
                idempotencyKey: 'order.cancelled:'.$locked->getKey(),
                subjectLabel: $locked->order_number,
            );

            return $locked->fresh(['history']);
        });
    }
}
