<?php

namespace App\Modules\Storefront\Application;

use Illuminate\Http\Request;

final class DevelopmentStorefrontAccess
{
    public function allows(Request $request): bool
    {
        return (bool) config('storefront.development_route_enabled', false)
            && in_array(app()->environment(), config('storefront.development_route_environments', []), true)
            && in_array(
                strtolower(rtrim($request->getHost(), '.')),
                config('storefront.development_hosts', []),
                true,
            );
    }
}
