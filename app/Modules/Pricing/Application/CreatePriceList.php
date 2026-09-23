<?php

namespace App\Modules\Pricing\Application;

use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreatePriceList
{
    /** @param array{name: string, code: string, currency: string} $attributes */
    public function handle(Tenant $tenant, array $attributes): PriceList
    {
        return DB::transaction(function () use ($tenant, $attributes): PriceList {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());

            return $lockedTenant->priceLists()->create([
                'name' => $attributes['name'],
                'code' => str($attributes['code'])->trim()->upper()->toString(),
                'currency' => str($attributes['currency'])->trim()->upper()->toString(),
                'status' => PriceListStatus::Active,
                'is_default' => false,
            ]);
        });
    }
}
