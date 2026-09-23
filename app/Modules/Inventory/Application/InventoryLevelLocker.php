<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InventoryLevelLocker
{
    public function lock(Tenant $tenant, string $locationId, string $variantId): InventoryLevel
    {
        return $this->lockMany($tenant, [[$locationId, $variantId]])->sole();
    }

    /**
     * Creates missing zero levels without a read/create race, then locks every row in a stable order.
     *
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return Collection<int, InventoryLevel>
     */
    public function lockMany(Tenant $tenant, array $pairs): Collection
    {
        if ($pairs === []) {
            throw new InvalidArgumentException('At least one inventory level must be locked.');
        }

        $pairs = collect($pairs)
            ->unique(fn (array $pair): string => $pair[0].':'.$pair[1])
            ->sortBy(fn (array $pair): string => $pair[0].':'.$pair[1])
            ->values();
        $now = now();

        foreach ($pairs as [$locationId, $variantId]) {
            DB::table('inventory_levels')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $tenant->getKey(),
                'inventory_location_id' => $locationId,
                'product_variant_id' => $variantId,
                'on_hand' => 0,
                'reserved' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return InventoryLevel::query()
            ->where('tenant_id', $tenant->getKey())
            ->where(function ($query) use ($pairs): void {
                foreach ($pairs as [$locationId, $variantId]) {
                    $query->orWhere(fn ($pair) => $pair
                        ->where('inventory_location_id', $locationId)
                        ->where('product_variant_id', $variantId));
                }
            })
            ->orderBy('inventory_location_id')
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get();
    }
}
