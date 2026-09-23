<?php

namespace App\Modules\Listings\Application;

use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Validation\ValidationException;

class SetStoreDefaultPriceList
{
    public function handle(Tenant $tenant, Store $store, ?string $priceListId): Store
    {
        $scoped = $tenant->stores()->findOrFail($store->getKey());
        if ($priceListId !== null && ! $tenant->priceLists()->where('status', PriceListStatus::Active)->whereKey($priceListId)->exists()) {
            throw ValidationException::withMessages(['default_price_list_id' => 'Select an active price list from the current company.']);
        }
        $scoped->update(['default_price_list_id' => $priceListId]);

        return $scoped->refresh();
    }
}
