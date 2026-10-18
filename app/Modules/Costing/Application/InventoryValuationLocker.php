<?php

namespace App\Modules\Costing\Application;

use App\Modules\Costing\Domain\Models\InventoryValuation;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InventoryValuationLocker
{
    public function lock(InventoryLevel $level): InventoryValuation
    {
        return $this->lockMany(collect([$level]))->sole();
    }

    /** @param Collection<int, InventoryLevel> $levels */
    public function lockMany(Collection $levels): Collection
    {
        $now = now();
        $levels = $levels->sortBy(fn (InventoryLevel $level): string => $level->inventory_location_id.':'.$level->product_variant_id)->values();
        foreach ($levels as $level) {
            DB::table('inventory_valuations')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $level->tenant_id,
                'inventory_location_id' => $level->inventory_location_id,
                'product_variant_id' => $level->product_variant_id,
                'average_unit_cost' => null,
                'currency' => null,
                'version' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return InventoryValuation::query()
            ->where('tenant_id', $levels->firstOrFail()->tenant_id)
            ->where(function ($query) use ($levels): void {
                foreach ($levels as $level) {
                    $query->orWhere(fn ($pair) => $pair
                        ->where('inventory_location_id', $level->inventory_location_id)
                        ->where('product_variant_id', $level->product_variant_id));
                }
            })
            ->orderBy('inventory_location_id')
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get();
    }
}
