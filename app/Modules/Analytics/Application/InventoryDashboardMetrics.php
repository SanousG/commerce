<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use Illuminate\Support\Facades\DB;

final class InventoryDashboardMetrics
{
    /** @return array<string, mixed> */
    public function build(string $tenantId): array
    {
        $threshold = max(0, (int) config('commerce.inventory.low_stock_threshold', 5));
        $availability = $this->availabilityQuery($tenantId);

        $counts = DB::query()->fromSub(clone $availability, 'stock')
            ->selectRaw('COUNT(*) FILTER (WHERE available <= 0) AS out_of_stock')
            ->selectRaw('COUNT(*) FILTER (WHERE available > 0 AND available <= ?) AS low_stock', [$threshold])
            ->first();

        $items = DB::query()->fromSub($availability, 'stock')
            ->where('available', '<=', $threshold)
            ->orderBy('available')
            ->orderBy('product_name')
            ->orderBy('sku')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'variant_id' => $row->variant_id,
                'product' => $row->product_name,
                'variant' => $row->variant_name,
                'sku' => $row->sku,
                'available' => (int) $row->available,
                'on_hand' => (int) $row->on_hand,
                'reserved' => (int) $row->reserved,
            ])->all();

        return [
            'threshold' => $threshold,
            'out_of_stock_variants' => (int) $counts->out_of_stock,
            'low_stock_variants' => (int) $counts->low_stock,
            'items' => $items,
        ];
    }

    private function availabilityQuery(string $tenantId)
    {
        return DB::table('product_variants')
            ->join('products', function ($join) {
                $join->on('products.id', '=', 'product_variants.product_id')
                    ->on('products.tenant_id', '=', 'product_variants.tenant_id');
            })
            ->leftJoin('inventory_levels', function ($join) {
                $join->on('inventory_levels.product_variant_id', '=', 'product_variants.id')
                    ->on('inventory_levels.tenant_id', '=', 'product_variants.tenant_id')
                    ->whereExists(fn ($query) => $query->selectRaw('1')
                        ->from('inventory_locations')
                        ->whereColumn('inventory_locations.id', 'inventory_levels.inventory_location_id')
                        ->whereColumn('inventory_locations.tenant_id', 'inventory_levels.tenant_id')
                        ->where('inventory_locations.status', InventoryLocationStatus::Active->value));
            })
            ->where('product_variants.tenant_id', $tenantId)
            ->where('product_variants.status', ProductVariantStatus::Active->value)
            ->where('products.status', ProductStatus::Active->value)
            ->groupBy(
                'product_variants.id', 'products.name', 'product_variants.name', 'product_variants.sku'
            )
            ->select([
                'product_variants.id as variant_id',
                'products.name as product_name',
                'product_variants.name as variant_name',
                'product_variants.sku',
            ])
            ->selectRaw('COALESCE(SUM(inventory_levels.on_hand), 0) AS on_hand')
            ->selectRaw('COALESCE(SUM(inventory_levels.reserved), 0) AS reserved')
            ->selectRaw('COALESCE(SUM(inventory_levels.on_hand - inventory_levels.reserved), 0) AS available');
    }
}
