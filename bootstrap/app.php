<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetUserLocale;
use App\Modules\Storefront\Http\Middleware\EnsureDevelopmentStorefrontRoute;
use App\Modules\Storefront\Http\Middleware\ResolvePublicStore;
use App\Modules\Stores\Http\Middleware\ResolveStore;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'store' => ResolveStore::class,
            'storefront.development' => EnsureDevelopmentStorefrontRoute::class,
            'storefront.public' => ResolvePublicStore::class,
            'tenant' => ResolveTenant::class,
        ]);

        $middleware->web(append: [
            SetUserLocale::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
