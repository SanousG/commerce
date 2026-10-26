<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductOptionValue;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveVariant
{
    /** @param array{sku: string, barcode?: ?string, name?: ?string, status: string, is_default?: bool, option_value_ids?: list<string>} $attributes */
    public function create(Tenant $tenant, Product $product, array $attributes): ProductVariant
    {
        return DB::transaction(function () use ($tenant, $product, $attributes): ProductVariant {
            $scopedProduct = $tenant->products()->lockForUpdate()->findOrFail($product->getKey());
            $values = $this->optionValues($scopedProduct, $attributes['option_value_ids'] ?? []);
            $makeDefault = $attributes['is_default'] ?? false;
            $this->assertDefaultIsActive($makeDefault, $attributes['status']);

            if ($makeDefault) {
                $this->deferDefaultConstraint();
                $scopedProduct->variants()->where('is_default', true)->update(['is_default' => false]);
            }

            $variant = $scopedProduct->variants()->create([
                'tenant_id' => $tenant->getKey(),
                'sku' => str($attributes['sku'])->trim()->upper()->toString(),
                'barcode' => filled($attributes['barcode'] ?? null) ? trim($attributes['barcode']) : null,
                'name' => $attributes['name'] ?? null,
                'status' => $attributes['status'],
                'is_default' => $makeDefault,
            ]);
            $this->syncOptionValues($variant, $values);

            if ($makeDefault) {
                $this->checkDefaultConstraint();
            }

            return $variant;
        });
    }

    /** @param array{sku: string, barcode?: ?string, name?: ?string, status: string, is_default?: bool, option_value_ids?: list<string>} $attributes */
    public function update(Tenant $tenant, Product $product, ProductVariant $variant, array $attributes): ProductVariant
    {
        return DB::transaction(function () use ($tenant, $product, $variant, $attributes): ProductVariant {
            $scopedProduct = $tenant->products()->lockForUpdate()->findOrFail($product->getKey());
            $scopedVariant = $scopedProduct->variants()->findOrFail($variant->getKey());
            $makeDefault = $attributes['is_default'] ?? false;
            $this->assertDefaultIsActive($makeDefault, $attributes['status']);

            if ($scopedVariant->is_default && ! $makeDefault) {
                throw ValidationException::withMessages(['is_default' => __('Choose another default variant before unsetting this one.')]);
            }

            if ($makeDefault && ! $scopedVariant->is_default) {
                $this->deferDefaultConstraint();
                $scopedProduct->variants()->where('is_default', true)->update(['is_default' => false]);
            }

            $scopedVariant->update([
                'sku' => str($attributes['sku'])->trim()->upper()->toString(),
                'barcode' => filled($attributes['barcode'] ?? null) ? trim($attributes['barcode']) : null,
                'name' => $attributes['name'] ?? null,
                'status' => $attributes['status'],
                'is_default' => $makeDefault,
            ]);

            if (array_key_exists('option_value_ids', $attributes)) {
                $this->syncOptionValues($scopedVariant, $this->optionValues($scopedProduct, $attributes['option_value_ids']));
            }

            if ($makeDefault) {
                $this->checkDefaultConstraint();
            }

            return $scopedVariant;
        });
    }

    /** @param list<string> $ids @return Collection<int, ProductOptionValue> */
    private function optionValues(Product $product, array $ids): Collection
    {
        $values = ProductOptionValue::query()
            ->where('product_id', $product->getKey())
            ->whereKey($ids)
            ->get();

        if ($values->count() !== count(array_unique($ids)) || $values->pluck('product_option_id')->unique()->count() !== $values->count()) {
            throw ValidationException::withMessages([
                'option_value_ids' => __('Choose at most one value from each option on this product.'),
            ]);
        }

        return $values;
    }

    /** @param Collection<int, ProductOptionValue> $values */
    private function syncOptionValues(ProductVariant $variant, Collection $values): void
    {
        $variant->optionValues()->sync($values->mapWithKeys(fn (ProductOptionValue $value): array => [
            $value->getKey() => [
                'product_id' => $variant->product_id,
                'product_option_id' => $value->product_option_id,
            ],
        ])->all());
    }

    private function assertDefaultIsActive(bool $isDefault, string $status): void
    {
        if ($isDefault && $status !== ProductVariantStatus::Active->value) {
            throw ValidationException::withMessages(['status' => __('The default variant must be active.')]);
        }
    }

    private function deferDefaultConstraint(): void
    {
        DB::statement('SET CONSTRAINTS products_default_variant_check, variants_default_variant_check DEFERRED');
    }

    private function checkDefaultConstraint(): void
    {
        DB::statement('SET CONSTRAINTS products_default_variant_check, variants_default_variant_check IMMEDIATE');
    }
}
