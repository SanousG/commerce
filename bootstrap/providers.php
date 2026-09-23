<?php

use App\Modules\Access\Providers\AccessServiceProvider;
use App\Modules\Stores\Providers\StoresServiceProvider;
use App\Modules\Tenancy\Providers\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    AccessServiceProvider::class,
    StoresServiceProvider::class,
];
