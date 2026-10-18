<?php

namespace App\Modules\Stores\Application;

use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Stores\Domain\Models\StoreSettings;
use App\Modules\Tenancy\Domain\Models\Tenant;

class GetStoreSettings
{
    public function handle(Tenant $tenant, string $storeId): Store
    {
        $store = $tenant->stores()->findOrFail($storeId);
        $store->settings()->firstOrCreate(
            ['tenant_id' => $tenant->getKey()],
            ['country_code' => 'PE', 'social_links' => StoreSettings::DEFAULT_SOCIAL_LINKS, 'primary_color' => '#17211B'],
        );

        return $store->load(['settings.logo', 'settings.favicon']);
    }
}
