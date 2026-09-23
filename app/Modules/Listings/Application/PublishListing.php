<?php

namespace App\Modules\Listings\Application;

use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishListing
{
    public function __construct(private readonly ListingPriceResolver $prices) {}

    public function handle(Tenant $tenant, Store $store, StoreListing $listing): StoreListing
    {
        return DB::transaction(function () use ($tenant, $store, $listing): StoreListing {
            $scoped = $store->listings()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($listing->getKey());
            $scoped->load(['store', 'product', 'variants.productVariant']);
            if ($scoped->status === StoreListingStatus::Archived) {
                throw ValidationException::withMessages(['listing' => 'Archived listings cannot be published.']);
            }
            if ($scoped->store->status !== StoreStatus::Active) {
                throw ValidationException::withMessages(['listing' => 'The current store must be active.']);
            }
            if ($scoped->product->status !== ProductStatus::Active) {
                throw ValidationException::withMessages(['listing' => 'The product must be active.']);
            }
            $enabled = $scoped->variants->filter(fn ($row) => $row->is_enabled && $row->productVariant->status === ProductVariantStatus::Active);
            if ($enabled->isEmpty()) {
                throw ValidationException::withMessages(['variants' => 'Enable at least one active variant.']);
            }
            if ($this->prices->priceListFor($scoped) === null) {
                throw ValidationException::withMessages(['price_list_id' => 'The effective price list must be active.']);
            }
            $missing = $enabled->filter(fn ($row) => $this->prices->priceFor($scoped, $row->productVariant) === null)
                ->map(fn ($row) => $row->productVariant->sku)->values()->all();
            if ($missing !== []) {
                throw ValidationException::withMessages(['variants' => 'Missing prices for enabled SKUs: '.implode(', ', $missing).'.']);
            }
            $scoped->update(['status' => StoreListingStatus::Published, 'published_at' => $scoped->published_at ?? now()]);

            return $scoped->refresh();
        });
    }
}
