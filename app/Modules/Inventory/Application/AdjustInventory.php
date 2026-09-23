<?php

namespace App\Modules\Inventory\Application;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustInventory
{
    public function __construct(
        private readonly InventoryLevelLocker $levels,
        private readonly InventoryWriter $writer,
    ) {}

    public function handle(
        Tenant $tenant,
        InventoryLocation $location,
        ProductVariant $variant,
        int $quantity,
        User $actor,
        string $reason,
    ): InventoryMovement {
        if ($quantity === 0) {
            throw ValidationException::withMessages(['quantity_delta' => 'The adjustment quantity cannot be zero.']);
        }

        return DB::transaction(function () use ($tenant, $location, $variant, $quantity, $actor, $reason): InventoryMovement {
            $scopedLocation = $tenant->inventoryLocations()->lockForUpdate()->findOrFail($location->getKey());
            $scopedVariant = ProductVariant::query()
                ->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($variant->getKey());

            if ($scopedLocation->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['location_id' => 'Inventory can only be adjusted at an active location.']);
            }

            $level = $this->levels->lock($tenant, $scopedLocation->getKey(), $scopedVariant->getKey());

            return $this->writer->change(
                $level,
                $quantity,
                0,
                $quantity > 0 ? InventoryMovementType::AdjustmentIn : InventoryMovementType::AdjustmentOut,
                $actor,
                $reason,
            );
        });
    }
}
