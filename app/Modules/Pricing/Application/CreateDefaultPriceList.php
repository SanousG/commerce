<?php

namespace App\Modules\Pricing\Application;

use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Tenancy\Domain\Models\Tenant;
use RuntimeException;

class CreateDefaultPriceList
{
    public function handle(Tenant $tenant): PriceList
    {
        $currency = str((string) config('commerce.default_currency', 'PEN'))->trim()->upper()->toString();
        if (! in_array($currency, config('commerce.currencies', []), true)) {
            throw new RuntimeException('The configured default commerce currency is unsupported.');
        }

        return $tenant->priceLists()->firstOrCreate(
            ['is_default' => true],
            [
                'name' => 'Precio principal',
                'code' => 'DEFAULT',
                'currency' => $currency,
                'status' => PriceListStatus::Active,
            ],
        );
    }
}
