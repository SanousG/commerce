<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateInventoryLocation
{
    /** @param array{name: string, code: string, status: string} $attributes */
    public function handle(Tenant $tenant, array $attributes): InventoryLocation
    {
        return DB::transaction(function () use ($tenant, $attributes): InventoryLocation {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());

            return $lockedTenant->inventoryLocations()->create([
                'name' => $attributes['name'],
                'code' => str($attributes['code'])->trim()->upper()->toString(),
                'status' => InventoryLocationStatus::from($attributes['status']),
                'is_default' => false,
            ]);
        });
    }
}
