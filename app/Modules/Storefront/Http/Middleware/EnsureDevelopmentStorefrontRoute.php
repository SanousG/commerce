<?php

namespace App\Modules\Storefront\Http\Middleware;

use App\Modules\Storefront\Application\DevelopmentStorefrontAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDevelopmentStorefrontRoute
{
    public function __construct(private readonly DevelopmentStorefrontAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->access->allows($request), 404);

        return $next($request);
    }
}
