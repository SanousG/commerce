<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantInventoryAvailability
{
    public function forVariant(Tenant $tenant, ProductVariant $variant): int
    {
        return $this->forVariants($tenant, collect([$variant]))->get($variant->getKey(), 0);
    }

    /** @param Collection<int, ProductVariant> $variants
     * @return Collection<string, int>
     */
    public function forVariants(Tenant $tenant, Collection $variants): Collection
    {
        $ids = $variants->pluck('id')->unique()->values();
        if ($tenant->productVariants()->whereKey($ids)->count() !== $ids->count()) {
            throw (new ModelNotFoundException)->setModel(ProductVariant::class, $ids->all());
        }

        $totals = DB::table('inventory_levels')
            ->join('inventory_locations', function ($join) {
                $join->on('inventory_locations.id', '=', 'inventory_levels.inventory_location_id')
                    ->on('inventory_locations.tenant_id', '=', 'inventory_levels.tenant_id');
            })
            ->where('inventory_levels.tenant_id', $tenant->getKey())
            ->where('inventory_locations.status', InventoryLocationStatus::Active->value)
            ->whereIn('inventory_levels.product_variant_id', $ids)
            ->groupBy('inventory_levels.product_variant_id')
            ->selectRaw('inventory_levels.product_variant_id, SUM(inventory_levels.on_hand - inventory_levels.reserved) AS available')
            ->pluck('available', 'product_variant_id');

        return $ids->mapWithKeys(fn (string $id): array => [$id => (int) ($totals[$id] ?? 0)]);
    }
}
