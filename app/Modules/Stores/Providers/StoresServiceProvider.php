<?php

namespace App\Modules\Stores\Providers;

use App\Modules\Stores\Application\StoreContext;
use Illuminate\Support\ServiceProvider;

class StoresServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(StoreContext::class, fn () => new StoreContext);
    }
}
