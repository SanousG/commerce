<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkShipmentReady
{
    public function handle(Tenant $tenant, Shipment $shipment, ?User $actor = null): Shipment
    {
        return DB::transaction(function () use ($tenant, $shipment, $actor): Shipment {
            $locked = Shipment::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($shipment->getKey());
            if ($locked->status === ShipmentStatus::Ready) {
                return $locked;
            }
            if ($locked->status !== ShipmentStatus::Pending) {
                throw ValidationException::withMessages(['shipment' => 'Only a pending shipment can be marked ready.']);
            }
            $locked->update(['status' => ShipmentStatus::Ready, 'ready_at' => now()]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => ShipmentStatus::Pending,
                'to_status' => ShipmentStatus::Ready, 'actor_user_id' => $actor?->getKey(),
            ]);

            return $locked->fresh('history');
        });
    }
}
