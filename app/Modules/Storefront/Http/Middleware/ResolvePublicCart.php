<?php

namespace App\Modules\Storefront\Http\Middleware;

use App\Modules\Storefront\Application\PublicCartContext;
use App\Modules\Storefront\Application\PublicCartIdentity;
use App\Modules\Storefront\Application\PublicStoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolvePublicCart
{
    public function __construct(
        private readonly PublicStoreContext $stores,
        private readonly PublicCartContext $carts,
        private readonly PublicCartIdentity $identity,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->carts->set($this->identity->resolve($request, $this->stores));

        try {
            return $next($request);
        } finally {
            $this->carts->clear();
        }
    }
}
