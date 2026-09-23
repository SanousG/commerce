<?php

namespace App\Modules\Listings\Application;

use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;

class ArchiveListing
{
    public function handle(Tenant $tenant, Store $store, StoreListing $listing): StoreListing
    {
        $scoped = $store->listings()->where('tenant_id', $tenant->getKey())->findOrFail($listing->getKey());
        $scoped->update(['status' => StoreListingStatus::Archived]);

        return $scoped->refresh();
    }
}
