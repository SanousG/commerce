<?php

namespace App\Modules\Listings\Application;

use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Stores\Domain\Enums\StoreStatus;

class ListingEligibility
{
    public function __construct(private readonly ListingPriceResolver $prices) {}

    public function evaluate(StoreListing $listing, ProductVariant $variant): EligibilityResult
    {
        $scoped = StoreListing::query()->with(['store', 'product'])->where('tenant_id', $listing->tenant_id)
            ->where('store_id', $listing->store_id)->findOrFail($listing->getKey());
        $scoped->load(['variants' => fn ($query) => $query->where('product_variant_id', $variant->getKey())]);

        $belongsToListing = $variant->tenant_id === $scoped->tenant_id && $variant->product_id === $scoped->product_id;

        return $this->evaluateLoaded(
            $scoped,
            $variant,
            $belongsToListing && $this->prices->priceFor($scoped, $variant) !== null,
        );
    }

    public function evaluateLoaded(StoreListing $listing, ProductVariant $variant, bool $hasPrice): EligibilityResult
    {
        $reasons = [];
        if ($listing->status !== StoreListingStatus::Published) {
            $reasons[] = 'LISTING_NOT_PUBLISHED';
        }
        if ($listing->product->status !== ProductStatus::Active) {
            $reasons[] = 'PRODUCT_INACTIVE';
        }
        if ($listing->store->status !== StoreStatus::Active) {
            $reasons[] = 'STORE_INACTIVE';
        }
        if ($variant->tenant_id !== $listing->tenant_id || $variant->product_id !== $listing->product_id) {
            $reasons[] = 'VARIANT_NOT_IN_PRODUCT';
        } else {
            if ($variant->status !== ProductVariantStatus::Active) {
                $reasons[] = 'VARIANT_INACTIVE';
            }
            $enabled = $listing->variants->contains(
                fn ($listingVariant) => $listingVariant->product_variant_id === $variant->getKey() && $listingVariant->is_enabled
            );
            if (! $enabled) {
                $reasons[] = 'VARIANT_DISABLED';
            }
            if (! $hasPrice) {
                $reasons[] = 'PRICE_MISSING';
            }
        }

        return new EligibilityResult($reasons === [], $reasons);
    }
}
