<?php

namespace App\Modules\Listings\Application;

use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Validation\ValidationException;

class UnpublishListing
{
    public function handle(Tenant $tenant, Store $store, StoreListing $listing): StoreListing
    {
        $scoped = $store->listings()->where('tenant_id', $tenant->getKey())->findOrFail($listing->getKey());
        if ($scoped->status === StoreListingStatus::Archived) {
            throw ValidationException::withMessages(['listing' => 'Archived listings cannot be unpublished.']);
        }
        $scoped->update(['status' => StoreListingStatus::Unpublished]);

        return $scoped->refresh();
    }
}
