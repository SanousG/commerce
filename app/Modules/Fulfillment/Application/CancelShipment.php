<?php

namespace App\Modules\Fulfillment\Application;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelShipment
{
    public function handle(
        Tenant $tenant,
        Shipment $shipment,
        ?string $reason = null,
        ?User $actor = null,
    ): Shipment {
        return DB::transaction(function () use ($tenant, $shipment, $reason, $actor): Shipment {
            $locked = Shipment::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($shipment->getKey());
            if ($locked->status === ShipmentStatus::Cancelled) {
                return $locked;
            }
            if (! in_array($locked->status, [ShipmentStatus::Pending, ShipmentStatus::Ready], true)) {
                throw ValidationException::withMessages(['shipment' => 'A shipped or delivered shipment cannot be cancelled.']);
            }
            $from = $locked->status;
            $locked->update(['status' => ShipmentStatus::Cancelled, 'cancelled_at' => now()]);
            $locked->history()->create([
                'tenant_id' => $tenant->getKey(), 'from_status' => $from, 'to_status' => ShipmentStatus::Cancelled,
                'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
            ]);

            return $locked->fresh('history');
        });
    }
}
