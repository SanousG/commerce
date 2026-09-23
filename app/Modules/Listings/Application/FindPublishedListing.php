<?php

namespace App\Modules\Listings\Application;

use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Stores\Domain\Models\Store;

class FindPublishedListing
{
    public function bySlug(Store $store, string $slug): StoreListing
    {
        return $store->listings()->where('tenant_id', $store->tenant_id)
            ->where('status', StoreListingStatus::Published)->where('storefront_slug', $slug)
            ->with('product')->firstOrFail();
    }
}
