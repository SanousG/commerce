<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReserveInventory
{
    public function __construct(private readonly InventoryLevelLocker $levels) {}

    public function handle(
        Tenant $tenant,
        InventoryLocation $location,
        ProductVariant $variant,
        int $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?DateTimeInterface $expiresAt = null,
    ): InventoryReservation {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'A reservation quantity must be positive.']);
        }
        if (($referenceType === null) !== ($referenceId === null)) {
            throw ValidationException::withMessages([
                'reference' => 'Reservation reference type and ID must be provided together.',
            ]);
        }

        return DB::transaction(function () use ($tenant, $location, $variant, $quantity, $referenceType, $referenceId, $expiresAt): InventoryReservation {
            $scopedLocation = $tenant->inventoryLocations()->lockForUpdate()->findOrFail($location->getKey());
            $scopedVariant = ProductVariant::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($variant->getKey());
            if ($scopedLocation->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['location' => 'Inventory can only be reserved at an active location.']);
            }

            $level = $this->levels->lock($tenant, $scopedLocation->getKey(), $scopedVariant->getKey());
            if ($level->available < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'There is insufficient available inventory.']);
            }

            $reservation = InventoryReservation::create([
                'tenant_id' => $tenant->getKey(),
                'inventory_location_id' => $scopedLocation->getKey(),
                'product_variant_id' => $scopedVariant->getKey(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quantity' => $quantity,
                'status' => InventoryReservationStatus::Active,
                'expires_at' => $expiresAt,
            ]);
            $level->update(['reserved' => $level->reserved + $quantity]);

            return $reservation;
        });
    }
}
