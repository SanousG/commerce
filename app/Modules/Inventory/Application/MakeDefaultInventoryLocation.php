<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MakeDefaultInventoryLocation
{
    public function handle(Tenant $tenant, InventoryLocation $location): InventoryLocation
    {
        return DB::transaction(function () use ($tenant, $location): InventoryLocation {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $scopedLocation = $lockedTenant->inventoryLocations()->lockForUpdate()->findOrFail($location->getKey());

            if ($scopedLocation->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['location' => 'Only an active inventory location can become default.']);
            }

            DB::statement('SET CONSTRAINTS inventory_locations_default_check DEFERRED');
            $lockedTenant->inventoryLocations()->where('is_default', true)->update(['is_default' => false]);
            $scopedLocation->update(['is_default' => true]);
            DB::statement('SET CONSTRAINTS inventory_locations_default_check IMMEDIATE');

            return $scopedLocation;
        });
    }
}
