<?php

namespace App\Modules\Listings\Application;

use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateListing
{
    /** @param array{product_id: string, storefront_slug?: ?string, price_list_id?: ?string} $attributes */
    public function handle(Tenant $tenant, Store $store, array $attributes): StoreListing
    {
        return DB::transaction(function () use ($tenant, $store, $attributes): StoreListing {
            $lockedStore = Store::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($store->getKey());
            if ($lockedStore->status !== StoreStatus::Active) {
                throw ValidationException::withMessages(['store' => 'The current store must be active.']);
            }
            $product = $tenant->products()->with('variants')->findOrFail($attributes['product_id']);
            if ($product->status !== ProductStatus::Active) {
                throw ValidationException::withMessages(['product_id' => 'Only active products can be listed.']);
            }
            if ($lockedStore->listings()->where('product_id', $product->getKey())->exists()) {
                throw ValidationException::withMessages(['product_id' => 'This product already has a listing in the current store.']);
            }
            $priceListId = $this->activePriceListId($tenant, $attributes['price_list_id'] ?? null);
            $slug = $this->uniqueSlug($lockedStore, $attributes['storefront_slug'] ?? $product->slug);
            $listing = $lockedStore->listings()->create([
                'tenant_id' => $tenant->getKey(), 'product_id' => $product->getKey(),
                'price_list_id' => $priceListId, 'status' => StoreListingStatus::Draft,
                'storefront_slug' => $slug,
            ]);
            foreach ($product->variants as $variant) {
                $listing->variants()->create([
                    'tenant_id' => $tenant->getKey(), 'product_id' => $product->getKey(),
                    'product_variant_id' => $variant->getKey(),
                    'is_enabled' => $variant->status === ProductVariantStatus::Active,
                ]);
            }

            return $listing;
        });
    }

    private function activePriceListId(Tenant $tenant, ?string $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $list = $tenant->priceLists()->where('status', PriceListStatus::Active)->find($id);
        if ($list === null) {
            throw ValidationException::withMessages(['price_list_id' => 'Select an active price list from the current company.']);
        }

        return $list->getKey();
    }

    private function uniqueSlug(Store $store, string $source): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            throw ValidationException::withMessages(['storefront_slug' => 'The storefront slug must contain letters or numbers.']);
        }
        $base = substr($base, 0, 180);
        $slug = $base;
        for ($suffix = 2; $store->listings()->where('storefront_slug', $slug)->exists(); $suffix++) {
            $suffixText = '-'.$suffix;
            $slug = rtrim(substr($base, 0, 180 - strlen($suffixText)), '-').$suffixText;
        }

        return $slug;
    }
}
