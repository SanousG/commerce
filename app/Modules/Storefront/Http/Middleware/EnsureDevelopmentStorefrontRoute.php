<?php

namespace App\Modules\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDevelopmentStorefrontRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) config('storefront.development_route_enabled', false);
        $environmentAllowed = in_array(
            app()->environment(),
            config('storefront.development_route_environments', []),
            true,
        );
        $host = strtolower(rtrim($request->getHost(), '.'));
        $hostAllowed = in_array($host, config('storefront.development_hosts', []), true);

        abort_unless($enabled && $environmentAllowed && $hostAllowed, 404);

        return $next($request);
    }
}
