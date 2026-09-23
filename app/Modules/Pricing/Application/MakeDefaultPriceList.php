<?php

namespace App\Modules\Pricing\Application;

use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MakeDefaultPriceList
{
    public function handle(Tenant $tenant, PriceList $priceList): PriceList
    {
        return DB::transaction(function () use ($tenant, $priceList): PriceList {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $scopedList = $lockedTenant->priceLists()->lockForUpdate()->findOrFail($priceList->getKey());

            if ($scopedList->status !== PriceListStatus::Active) {
                throw ValidationException::withMessages(['price_list' => 'Only an active price list can become default.']);
            }

            DB::statement('SET CONSTRAINTS price_lists_default_check DEFERRED');
            $lockedTenant->priceLists()->where('is_default', true)->update(['is_default' => false]);
            $scopedList->update(['is_default' => true]);
            DB::statement('SET CONSTRAINTS price_lists_default_check IMMEDIATE');

            return $scopedList;
        });
    }
}
