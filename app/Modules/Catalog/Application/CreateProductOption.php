<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductOption;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductOption
{
    /** @param array{name: string, position?: int, values: list<string>} $attributes */
    public function handle(Tenant $tenant, Product $product, array $attributes): ProductOption
    {
        return DB::transaction(function () use ($tenant, $product, $attributes): ProductOption {
            $scopedProduct = $tenant->products()->lockForUpdate()->findOrFail($product->getKey());

            if ($scopedProduct->options()->where('name', $attributes['name'])->exists()) {
                throw ValidationException::withMessages(['name' => __('This product already has an option with that name.')]);
            }

            $option = $scopedProduct->options()->create([
                'name' => $attributes['name'],
                'position' => $attributes['position'] ?? $scopedProduct->options()->count(),
            ]);

            foreach (array_values($attributes['values']) as $position => $value) {
                $option->values()->create([
                    'product_id' => $scopedProduct->getKey(),
                    'value' => $value,
                    'position' => $position,
                ]);
            }

            return $option;
        });
    }
}
