<?php

namespace App\Modules\Storefront\Providers;

use App\Modules\Storefront\Application\PublicCartContext;
use App\Modules\Storefront\Application\PublicStoreContext;
use Illuminate\Support\ServiceProvider;

final class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PublicStoreContext::class, fn () => new PublicStoreContext);
        $this->app->scoped(PublicCartContext::class, fn () => new PublicCartContext);
    }
}
