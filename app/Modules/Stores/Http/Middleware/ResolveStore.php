<?php

namespace App\Modules\Stores\Http\Middleware;

use App\Modules\Stores\Application\StoreAccess;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStore
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
        private readonly StoreAccess $storeAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $tenant = $this->tenantContext->get();
        abort_unless($this->storeAccess->canSelect($request->user(), $tenant), 403);

        $storeId = $request->session()->get(StoreContext::SESSION_KEY);
        $store = null;

        if ($storeId !== null) {
            $store = $tenant->stores()
                ->whereKey($storeId)
                ->where('status', StoreStatus::Active)
                ->first();

            if ($store === null) {
                $request->session()->forget(StoreContext::SESSION_KEY);
            }
        }

        if ($store === null) {
            $stores = $tenant->stores()
                ->where('status', StoreStatus::Active)
                ->limit(2)
                ->get();

            if ($stores->isEmpty()) {
                return $this->storeAccess->canCreate($request->user(), $tenant)
                    ? to_route('admin.stores.create')
                    : to_route('admin.stores.index');
            }

            if ($stores->count() > 1) {
                return to_route('admin.stores.index');
            }

            $store = $stores->first();
            $request->session()->put(StoreContext::SESSION_KEY, $store->getKey());
        }

        $this->storeContext->set($store);

        try {
            return $next($request);
        } finally {
            $this->storeContext->clear();
        }
    }
}
