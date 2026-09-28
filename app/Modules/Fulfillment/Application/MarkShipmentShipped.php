<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkShipmentShipped
{
    public function handle(Tenant $tenant, Shipment $shipment, ?User $actor = null): Shipment
    {
        return DB::transaction(function () use ($tenant, $shipment, $actor): Shipment {
            $fulfillmentId = Shipment::query()->where('tenant_id', $tenant->getKey())
                ->whereKey($shipment->getKey())->valueOrFail('fulfillment_id');
            $fulfillment = Fulfillment::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($fulfillmentId);
            $locked = Shipment::query()->where('tenant_id', $tenant->getKey())
                ->where('fulfillment_id', $fulfillment->getKey())->lockForUpdate()->findOrFail($shipment->getKey());
            if ($locked->status === ShipmentStatus::Shipped) {
                return $locked;
            }
            if ($locked->status !== ShipmentStatus::Ready) {
                throw ValidationException::withMessages(['shipment' => 'Only a ready shipment can be shipped.']);
            }
            if ($fulfillment->status !== FulfillmentStatus::Fulfilled) {
                throw ValidationException::withMessages(['fulfillment' => 'The fulfillment must be fulfilled before shipment.']);
            }
            $locked->update(['status' => ShipmentStatus::Shipped, 'shipped_at' => now()]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => ShipmentStatus::Ready,
                'to_status' => ShipmentStatus::Shipped, 'actor_user_id' => $actor?->getKey(),
            ]);

            return $locked->fresh('history');
        });
    }
}
