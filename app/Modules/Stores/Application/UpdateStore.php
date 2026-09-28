<?php

namespace App\Modules\Stores\Application;

use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class UpdateStore
{
    /** @param array{name: string, status: string, currency: string, timezone: string, locale: string} $attributes */
    public function handle(Tenant $tenant, string $storeId, array $attributes): Store
    {
        return DB::transaction(function () use ($tenant, $storeId, $attributes): Store {
            $store = $tenant->stores()->lockForUpdate()->findOrFail($storeId);
            $store->update($attributes);

            return $store;
        });
    }
}
