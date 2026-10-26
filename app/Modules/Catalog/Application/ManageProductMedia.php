<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductMedia;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Catalog\Domain\Models\ProductVariantMedia;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageProductMedia
{
    public function attachProduct(Tenant $tenant, Product $product, MediaAsset $asset, bool $primary, ?string $altText): ProductMedia
    {
        return DB::transaction(function () use ($tenant, $product, $asset, $primary, $altText): ProductMedia {
            MediaAsset::query()->where('tenant_id', $tenant->getKey())->where('status', MediaStatus::Ready)
                ->lockForUpdate()->findOrFail($asset->getKey());
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            if ($primary) {
                $product->media()->update(['is_primary' => false]);
            }
            $existing = $product->media()->where('media_asset_id', $asset->getKey())->first();
            if ($existing) {
                $existing->update(['is_primary' => $primary ?: $existing->is_primary, 'alt_text' => $this->nullable($altText)]);

                return $existing->refresh();
            }

            return $product->media()->create([
                'tenant_id' => $tenant->getKey(), 'media_asset_id' => $asset->getKey(),
                'is_primary' => $primary, 'position' => ((int) $product->media()->max('position')) + 1,
                'alt_text' => $this->nullable($altText),
            ]);
        });
    }

    /** @param list<string> $orderedIds */
    public function reorderProduct(Product $product, array $orderedIds, ?string $primaryId): void
    {
        DB::transaction(function () use ($product, $orderedIds, $primaryId): void {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $current = $product->media()->pluck('media_asset_id')->all();
            if (array_diff($current, $orderedIds) !== [] || array_diff($orderedIds, $current) !== [] || count($orderedIds) !== count($current)) {
                throw ValidationException::withMessages(['media_asset_ids' => __('The order must contain every attached image exactly once.')]);
            }
            if ($primaryId !== null && ! in_array($primaryId, $current, true)) {
                throw ValidationException::withMessages(['primary_media_asset_id' => __('The primary image must be attached to this product.')]);
            }
            $product->media()->update(['is_primary' => false]);
            foreach ($orderedIds as $position => $id) {
                $product->media()->where('media_asset_id', $id)->update([
                    'position' => $position, 'is_primary' => $id === $primaryId,
                ]);
            }
        });
    }

    public function detachProduct(Product $product, MediaAsset $asset): void
    {
        $product->media()->where('media_asset_id', $asset->getKey())->delete();
    }

    public function attachVariant(Tenant $tenant, ProductVariant $variant, MediaAsset $asset): ProductVariantMedia
    {
        return DB::transaction(function () use ($tenant, $variant, $asset): ProductVariantMedia {
            MediaAsset::query()->where('tenant_id', $tenant->getKey())->where('status', MediaStatus::Ready)
                ->lockForUpdate()->findOrFail($asset->getKey());
            ProductVariant::query()->whereKey($variant->getKey())->lockForUpdate()->firstOrFail();

            return $variant->media()->firstOrCreate(['media_asset_id' => $asset->getKey()], [
                'tenant_id' => $tenant->getKey(), 'position' => ((int) $variant->media()->max('position')) + 1,
            ]);
        });
    }

    /** @param list<string> $orderedIds */
    public function reorderVariant(ProductVariant $variant, array $orderedIds): void
    {
        DB::transaction(function () use ($variant, $orderedIds): void {
            ProductVariant::query()->whereKey($variant->getKey())->lockForUpdate()->firstOrFail();
            $current = $variant->media()->pluck('media_asset_id')->all();
            if (array_diff($current, $orderedIds) !== [] || array_diff($orderedIds, $current) !== [] || count($orderedIds) !== count($current)) {
                throw ValidationException::withMessages(['media_asset_ids' => __('The order must contain every attached variant image exactly once.')]);
            }
            foreach ($orderedIds as $position => $id) {
                $variant->media()->where('media_asset_id', $id)->update(['position' => $position]);
            }
        });
    }

    public function detachVariant(ProductVariant $variant, MediaAsset $asset): void
    {
        $variant->media()->where('media_asset_id', $asset->getKey())->delete();
    }

    private function nullable(?string $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
