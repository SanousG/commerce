<?php

namespace App\Modules\Pricing\Application;

use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchivePriceList
{
    public function handle(Tenant $tenant, PriceList $priceList): PriceList
    {
        return DB::transaction(function () use ($tenant, $priceList): PriceList {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $scopedList = $lockedTenant->priceLists()->lockForUpdate()->findOrFail($priceList->getKey());

            if ($scopedList->is_default) {
                throw ValidationException::withMessages(['price_list' => 'Choose another default before archiving this list.']);
            }

            $scopedList->update(['status' => PriceListStatus::Archived]);

            return $scopedList;
        });
    }
}
