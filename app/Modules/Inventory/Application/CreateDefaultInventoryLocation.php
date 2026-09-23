<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use RuntimeException;

class CreateDefaultInventoryLocation
{
    public function handle(Tenant $tenant): InventoryLocation
    {
        $name = trim((string) config('commerce.default_inventory_location_name', 'Almacén principal'));
        $code = str((string) config('commerce.default_inventory_location_code', 'MAIN'))->trim()->upper()->toString();
        if ($name === '' || strlen($name) > 120 || strlen($code) > 40
            || preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $code) !== 1) {
            throw new RuntimeException('The configured default inventory location is invalid.');
        }

        return $tenant->inventoryLocations()->firstOrCreate(
            ['is_default' => true],
            [
                'name' => $name,
                'code' => $code,
                'status' => InventoryLocationStatus::Active,
            ],
        );
    }
}
