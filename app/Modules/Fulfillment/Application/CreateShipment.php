<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateShipment
{
    public function handle(
        Tenant $tenant,
        Fulfillment $fulfillment,
        ?string $carrierName = null,
        ?string $serviceName = null,
        ?string $trackingNumber = null,
        ?string $trackingUrl = null,
        ?User $actor = null,
    ): Shipment {
        return DB::transaction(function () use ($tenant, $fulfillment, $carrierName, $serviceName, $trackingNumber, $trackingUrl, $actor): Shipment {
            $orderId = Fulfillment::query()->where('tenant_id', $tenant->getKey())
                ->whereKey($fulfillment->getKey())->valueOrFail('order_id');
            $order = Order::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($orderId);
            $locked = Fulfillment::query()->where('tenant_id', $tenant->getKey())
                ->where('order_id', $order->getKey())->lockForUpdate()->findOrFail($fulfillment->getKey());
            $existing = $locked->shipment()->first();
            if ($existing !== null) {
                return $existing;
            }
            if ($locked->status === FulfillmentStatus::Cancelled) {
                throw ValidationException::withMessages(['fulfillment' => 'A cancelled fulfillment cannot be shipped.']);
            }
            if ($order->delivery_type !== 'SHIPPING') {
                throw ValidationException::withMessages(['shipment' => 'Shipments can only be created for shipping orders.']);
            }
            $shipment = Shipment::query()->create([
                'tenant_id' => $tenant->getKey(), 'store_id' => $locked->store_id, 'order_id' => $locked->order_id,
                'fulfillment_id' => $locked->getKey(), 'status' => ShipmentStatus::Pending,
                'carrier_name' => $carrierName, 'service_name' => $serviceName,
                'tracking_number' => $trackingNumber, 'tracking_url' => $trackingUrl,
                'created_by_user_id' => $actor?->getKey(),
            ]);
            $shipment->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => null, 'to_status' => ShipmentStatus::Pending,
                'actor_user_id' => $actor?->getKey(),
            ]);

            return $shipment->load('history');
        });
    }
}
