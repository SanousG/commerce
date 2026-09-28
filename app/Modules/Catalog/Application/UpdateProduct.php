<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProduct
{
    /** @param array{name: string, description?: ?string, status: string, tax_category_id?: ?string, categories?: list<string>} $attributes */
    public function handle(Tenant $tenant, Product $product, array $attributes): Product
    {
        return DB::transaction(function () use ($tenant, $product, $attributes): Product {
            $lockedProduct = $tenant->products()->lockForUpdate()->findOrFail($product->getKey());
            $categoryIds = $attributes['categories'] ?? [];

            if ($tenant->categories()->whereKey($categoryIds)->count() !== count(array_unique($categoryIds))) {
                throw ValidationException::withMessages(['categories' => 'Every category must belong to the current company.']);
            }

            $lockedProduct->update([
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'],
                ...array_key_exists('tax_category_id', $attributes)
                    ? ['tax_category_id' => $attributes['tax_category_id']]
                    : [],
            ]);
            $lockedProduct->categories()->sync(collect($categoryIds)->mapWithKeys(
                fn (string $id): array => [$id => ['tenant_id' => $tenant->getKey()]],
            )->all());

            return $lockedProduct;
        });
    }
}
