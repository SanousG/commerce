<?php

namespace App\Modules\Stores\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stores\Application\CreateStore;
use App\Modules\Stores\Application\StoreAccess;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Http\Requests\CreateStoreRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, StoreAccess $access): Response
    {
        abort_unless($access->canView($request->user(), $tenantContext->get()), 403);

        $stores = $tenantContext->get()->stores()
            ->orderBy('name')
            ->get()
            ->map(fn ($store) => [
                'id' => $store->getKey(),
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status->value,
                'currency' => $store->currency,
                'timezone' => $store->timezone,
                'locale' => $store->locale,
                'selected' => $request->session()->get(StoreContext::SESSION_KEY) === $store->getKey(),
            ]);

        return Inertia::render('admin/stores/Index', [
            'stores' => $stores,
        ]);
    }

    public function create(Request $request, TenantContext $tenantContext, StoreAccess $access): Response
    {
        abort_unless($access->canCreate($request->user(), $tenantContext->get()), 403);

        return Inertia::render('admin/stores/Create');
    }

    public function store(
        CreateStoreRequest $request,
        TenantContext $tenantContext,
        CreateStore $createStore,
    ): RedirectResponse {
        $tenant = $tenantContext->get();
        $result = $createStore->handle($tenant, $request->safe()->only([
            'name',
            'currency',
            'timezone',
            'locale',
        ]));

        if ($result->isFirstStore) {
            $request->session()->put(StoreContext::SESSION_KEY, $result->store->getKey());
        }

        return to_route('admin.dashboard');
    }

    public function select(
        Request $request,
        TenantContext $tenantContext,
        StoreAccess $access,
        string $store,
    ): RedirectResponse {
        abort_unless($access->canSelect($request->user(), $tenantContext->get()), 403);

        $selectedStore = $tenantContext->get()->stores()
            ->whereKey($store)
            ->where('status', StoreStatus::Active)
            ->firstOrFail();

        $request->session()->put(StoreContext::SESSION_KEY, $selectedStore->getKey());

        return to_route('admin.dashboard');
    }
}
