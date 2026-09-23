<?php

namespace App\Modules\Listings\Application;

use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateListing
{
    /** @param array{storefront_slug: string, price_list_id?: ?string, enabled_variant_ids: list<string>} $attributes */
    public function handle(Tenant $tenant, Store $store, StoreListing $listing, array $attributes): StoreListing
    {
        return DB::transaction(function () use ($tenant, $store, $listing, $attributes): StoreListing {
            Store::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($store->getKey());
            $scoped = $store->listings()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($listing->getKey());
            if ($scoped->status === StoreListingStatus::Archived) {
                throw ValidationException::withMessages(['listing' => 'Archived listings cannot be changed.']);
            }
            $slug = Str::slug($attributes['storefront_slug']);
            if ($slug === '') {
                throw ValidationException::withMessages(['storefront_slug' => 'The storefront slug must contain letters or numbers.']);
            }
            if ($store->listings()->where('storefront_slug', $slug)->whereKeyNot($scoped->getKey())->exists()) {
                throw ValidationException::withMessages(['storefront_slug' => 'This slug is already used in the current store.']);
            }
            $priceListId = $attributes['price_list_id'] ?? null;
            if ($priceListId !== null && ! $tenant->priceLists()->where('status', PriceListStatus::Active)->whereKey($priceListId)->exists()) {
                throw ValidationException::withMessages(['price_list_id' => 'Select an active price list from the current company.']);
            }
            $enabled = array_values(array_unique($attributes['enabled_variant_ids']));
            $variants = $tenant->productVariants()->where('product_id', $scoped->product_id)->get();
            if ($variants->whereIn('id', $enabled)->count() !== count($enabled)) {
                throw ValidationException::withMessages(['enabled_variant_ids' => 'Every enabled variant must belong to the listing product.']);
            }
            $scoped->update(['storefront_slug' => $slug, 'price_list_id' => $priceListId]);
            foreach ($variants as $variant) {
                $scoped->variants()->updateOrCreate(
                    ['product_variant_id' => $variant->getKey()],
                    ['tenant_id' => $tenant->getKey(), 'product_id' => $scoped->product_id, 'is_enabled' => in_array($variant->getKey(), $enabled, true)],
                );
            }

            return $scoped->refresh();
        });
    }
}
