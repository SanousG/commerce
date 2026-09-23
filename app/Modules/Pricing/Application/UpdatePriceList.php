<?php

namespace App\Modules\Pricing\Application;

use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePriceList
{
    /** @param array{name: string, currency: string, status: string} $attributes */
    public function handle(Tenant $tenant, PriceList $priceList, array $attributes): PriceList
    {
        return DB::transaction(function () use ($tenant, $priceList, $attributes): PriceList {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $scopedList = $lockedTenant->priceLists()->lockForUpdate()->findOrFail($priceList->getKey());
            $currency = str($attributes['currency'])->trim()->upper()->toString();

            if ($currency !== $scopedList->currency && $scopedList->prices()->exists()) {
                throw ValidationException::withMessages([
                    'currency' => 'Currency cannot change after prices have been assigned.',
                ]);
            }
            if ($scopedList->status === PriceListStatus::Archived) {
                throw ValidationException::withMessages(['status' => 'Archived price lists cannot be edited.']);
            }
            if ($scopedList->is_default && $attributes['status'] !== PriceListStatus::Active->value) {
                throw ValidationException::withMessages(['status' => 'The default price list must remain active.']);
            }

            $scopedList->update([
                'name' => $attributes['name'],
                'currency' => $currency,
                'status' => $attributes['status'],
            ]);

            return $scopedList;
        });
    }
}
