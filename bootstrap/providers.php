<?php

use App\Modules\Access\Providers\AccessServiceProvider;
use App\Modules\Fiscal\Providers\FiscalServiceProvider;
use App\Modules\Storefront\Providers\StorefrontServiceProvider;
use App\Modules\Stores\Providers\StoresServiceProvider;
use App\Modules\Tenancy\Providers\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    AccessServiceProvider::class,
    StoresServiceProvider::class,
    StorefrontServiceProvider::class,
    FiscalServiceProvider::class,
];
