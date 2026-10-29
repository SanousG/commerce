<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Checkout\Domain\Models\CheckoutLine;
use App\Modules\Inventory\Application\InventoryLevelLocker;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CheckoutInventoryAllocator
{
    public const REFERENCE_TYPE = 'CHECKOUT_LINE';

    public function __construct(private readonly InventoryLevelLocker $levels) {}

    /** @param Collection<int, CheckoutLine> $lines */
    public function reserve(
        Tenant $tenant,
        Collection $lines,
        DateTimeInterface $expiresAt,
        ?string $inventoryLocationId = null,
    ): void
    {
        $locationsQuery = $tenant->inventoryLocations()->where('status', InventoryLocationStatus::Active);
        if ($inventoryLocationId !== null) {
            $locationsQuery->whereKey($inventoryLocationId);
        }
        $locations = $locationsQuery->orderBy('id')->lockForUpdate()->get();
        if ($locations->isEmpty()) {
            throw ValidationException::withMessages([
                'inventory' => $inventoryLocationId === null
                    ? 'No active inventory location is available.'
                    : 'The selected pickup inventory location is not available.',
            ]);
        }

        $variantIds = $lines->pluck('product_variant_id')->unique()->sort()->values();
        $variants = ProductVariant::query()->where('tenant_id', $tenant->getKey())->whereKey($variantIds)
            ->orderBy('id')->lockForUpdate()->get();
        if ($variants->count() !== $variantIds->count()) {
            throw ValidationException::withMessages(['inventory' => 'A checkout variant is invalid.']);
        }

        // Match TransferInventory: locations, variants, then all level rows in stable pair order.
        $pairs = $locations->flatMap(fn (InventoryLocation $location) => $variantIds
            ->map(fn (string $variantId): array => [$location->getKey(), $variantId]))->all();
        $levels = $this->levels->lockMany($tenant, $pairs)->keyBy(
            fn (InventoryLevel $level): string => $level->inventory_location_id.':'.$level->product_variant_id,
        );
        $allocationOrder = $locations->sortBy(fn (InventoryLocation $location): string => sprintf(
            '%d:%s:%s', $location->is_default ? 0 : 1, $location->code, $location->getKey(),
        ))->values();

        foreach ($lines->sortBy('product_variant_id') as $line) {
            $remaining = $line->quantity;
            foreach ($allocationOrder as $location) {
                $level = $levels[$location->getKey().':'.$line->product_variant_id];
                $quantity = min($remaining, $level->available);
                if ($quantity <= 0) {
                    continue;
                }
                InventoryReservation::create([
                    'tenant_id' => $tenant->getKey(), 'inventory_location_id' => $location->getKey(),
                    'product_variant_id' => $line->product_variant_id, 'reference_type' => self::REFERENCE_TYPE,
                    'reference_id' => $line->getKey(), 'quantity' => $quantity,
                    'status' => InventoryReservationStatus::Active, 'expires_at' => $expiresAt,
                ]);
                $level->reserved += $quantity;
                $level->save();
                $remaining -= $quantity;
                if ($remaining === 0) {
                    break;
                }
            }
            if ($remaining > 0) {
                throw ValidationException::withMessages(['inventory' => "Insufficient inventory for SKU {$line->sku}."]);
            }
        }
    }
}
