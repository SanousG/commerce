<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelFulfillment
{
    public function __construct(private readonly OrderFulfillmentStatusUpdater $statusUpdater) {}

    public function handle(
        Tenant $tenant,
        Fulfillment $fulfillment,
        ?string $reason = null,
        ?User $actor = null,
    ): Fulfillment {
        return DB::transaction(function () use ($tenant, $fulfillment, $reason, $actor): Fulfillment {
            $orderId = Fulfillment::query()->where('tenant_id', $tenant->getKey())
                ->whereKey($fulfillment->getKey())->valueOrFail('order_id');
            $order = Order::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($orderId);
            $locked = Fulfillment::query()->where('tenant_id', $tenant->getKey())
                ->where('order_id', $order->getKey())->lockForUpdate()->findOrFail($fulfillment->getKey());
            if ($locked->status === FulfillmentStatus::Cancelled) {
                return $locked;
            }
            if (! in_array($locked->status, [FulfillmentStatus::Pending, FulfillmentStatus::Processing], true)) {
                throw ValidationException::withMessages(['fulfillment' => 'A fulfilled fulfillment cannot be cancelled.']);
            }
            $from = $locked->status;
            $shipment = $locked->shipment()->lockForUpdate()->first();
            if ($shipment !== null && in_array($shipment->status, [ShipmentStatus::Pending, ShipmentStatus::Ready], true)) {
                $shipmentFrom = $shipment->status;
                $shipment->update(['status' => ShipmentStatus::Cancelled, 'cancelled_at' => now()]);
                $shipment->history()->create([
                    'tenant_id' => $tenant->getKey(), 'from_status' => $shipmentFrom,
                    'to_status' => ShipmentStatus::Cancelled, 'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
                ]);
            }
            $locked->update([
                'status' => FulfillmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => $from, 'to_status' => FulfillmentStatus::Cancelled,
                'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
            ]);
            $this->statusUpdater->update($order);

            return $locked->fresh(['items', 'history']);
        });
    }
}
