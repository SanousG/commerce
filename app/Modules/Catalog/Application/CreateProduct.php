<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProduct
{
    /**
     * @param  array{name: string, description?: ?string, sku: string, barcode?: ?string, categories?: list<string>}  $attributes
     */
    public function handle(Tenant $tenant, array $attributes): Product
    {
        return DB::transaction(function () use ($tenant, $attributes): Product {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $categoryIds = $attributes['categories'] ?? [];
            $this->validateCategories($lockedTenant, $categoryIds);

            DB::statement('SET CONSTRAINTS products_default_variant_check, variants_default_variant_check DEFERRED');

            $product = $lockedTenant->products()->create([
                'name' => $attributes['name'],
                'slug' => $this->uniqueSlug($lockedTenant, $attributes['name']),
                'description' => $attributes['description'] ?? null,
                'status' => ProductStatus::Draft,
            ]);

            $product->variants()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'name' => 'Default',
                'sku' => str($attributes['sku'])->trim()->upper()->toString(),
                'barcode' => filled($attributes['barcode'] ?? null) ? trim($attributes['barcode']) : null,
                'is_default' => true,
                'status' => ProductVariantStatus::Active,
            ]);

            $this->syncCategories($product, $lockedTenant, $categoryIds);
            DB::statement('SET CONSTRAINTS products_default_variant_check, variants_default_variant_check IMMEDIATE');

            return $product;
        });
    }

    /** @param list<string> $categoryIds */
    private function validateCategories(Tenant $tenant, array $categoryIds): void
    {
        if ($tenant->categories()->whereKey($categoryIds)->count() !== count(array_unique($categoryIds))) {
            throw ValidationException::withMessages(['categories' => 'Every category must belong to the current company.']);
        }
    }

    /** @param list<string> $categoryIds */
    private function syncCategories(Product $product, Tenant $tenant, array $categoryIds): void
    {
        $product->categories()->sync(collect($categoryIds)->mapWithKeys(
            fn (string $id): array => [$id => ['tenant_id' => $tenant->getKey()]],
        )->all());
    }

    private function uniqueSlug(Tenant $tenant, string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while ($tenant->products()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
