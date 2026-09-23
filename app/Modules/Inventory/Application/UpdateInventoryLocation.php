<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInventoryLocation
{
    /** @param array{name: string, code: string, status: string} $attributes */
    public function handle(Tenant $tenant, InventoryLocation $location, array $attributes): InventoryLocation
    {
        return DB::transaction(function () use ($tenant, $location, $attributes): InventoryLocation {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $scopedLocation = $lockedTenant->inventoryLocations()->lockForUpdate()->findOrFail($location->getKey());
            $status = InventoryLocationStatus::from($attributes['status']);

            if ($scopedLocation->is_default && $status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['status' => 'The default inventory location must remain active.']);
            }

            $scopedLocation->update([
                'name' => $attributes['name'],
                'code' => str($attributes['code'])->trim()->upper()->toString(),
                'status' => $status,
            ]);

            return $scopedLocation;
        });
    }
}
