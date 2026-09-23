<?php

namespace App\Modules\Tenancy\Providers;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
    }
}
