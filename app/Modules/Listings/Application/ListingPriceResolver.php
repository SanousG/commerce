<?php

namespace App\Modules\Listings\Application;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Pricing\Application\PriceResolver;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Pricing\Domain\Models\VariantPrice;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class ListingPriceResolver
{
    public function __construct(private readonly PriceResolver $prices) {}

    public function priceListFor(StoreListing $listing): ?PriceList
    {
        $scoped = StoreListing::query()->with('store')->where('tenant_id', $listing->tenant_id)
            ->where('store_id', $listing->store_id)->findOrFail($listing->getKey());
        $priceListId = $scoped->price_list_id ?? $scoped->store->default_price_list_id;

        $query = PriceList::query()->where('tenant_id', $scoped->tenant_id)->where('status', PriceListStatus::Active);

        return $priceListId === null
            ? $query->where('is_default', true)->first()
            : $query->whereKey($priceListId)->first();
    }

    public function priceFor(StoreListing $listing, ProductVariant $variant): ?VariantPrice
    {
        if ($variant->tenant_id !== $listing->tenant_id || $variant->product_id !== $listing->product_id) {
            throw (new ModelNotFoundException)->setModel(ProductVariant::class, [$variant->getKey()]);
        }

        $list = $this->priceListFor($listing);

        return $list === null ? null : $this->prices->priceFor($listing->tenant()->firstOrFail(), $variant, $list);
    }

    /** @param Collection<int, StoreListing> $listings
     * @return Collection<string, PriceList|null>
     */
    public function priceListsFor(Collection $listings): Collection
    {
        if ($listings->isEmpty()) {
            return collect();
        }

        $tenantId = $listings->first()->tenant_id;
        if ($listings->contains(fn (StoreListing $listing) => $listing->tenant_id !== $tenantId)) {
            throw (new ModelNotFoundException)->setModel(StoreListing::class, $listings->pluck('id')->all());
        }

        $listings = new EloquentCollection($listings->all());
        $listings->loadMissing('store');
        $ids = $listings->flatMap(fn (StoreListing $listing): array => array_filter([
            $listing->price_list_id,
            $listing->store->default_price_list_id,
        ]))->unique();
        $lists = PriceList::query()->where('tenant_id', $tenantId)->where('status', PriceListStatus::Active)
            ->where(function ($query) use ($ids) {
                $query->whereIn('id', $ids)->orWhere('is_default', true);
            })->get();
        $default = $lists->firstWhere('is_default', true);
        $byId = $lists->keyBy('id');

        return $listings->mapWithKeys(function (StoreListing $listing) use ($byId, $default): array {
            $id = $listing->price_list_id ?? $listing->store->default_price_list_id;

            return [$listing->getKey() => $id === null ? $default : $byId->get($id)];
        });
    }
}
