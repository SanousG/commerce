<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\AdminSetupStatus;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Application\UpdateTenantSettings;
use App\Modules\Tenancy\Http\Requests\UpdateTenantSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, AdminSetupStatus $setupStatus): Response
    {
        $tenant = $tenantContext->get();
        $store = $tenant->stores()->whereKey($request->session()->get(StoreContext::SESSION_KEY))->first();

        return Inertia::render('admin/settings/Index', [
            'setup' => $setupStatus->for($tenant, $store),
            'store' => $store?->only(['id', 'name', 'currency', 'timezone', 'locale']),
        ]);
    }

    public function general(TenantContext $tenantContext): Response
    {
        return Inertia::render('admin/settings/General', [
            'tenant' => $tenantContext->get()->only(['name', 'slug', 'status']),
        ]);
    }

    public function update(
        UpdateTenantSettingsRequest $request,
        TenantContext $tenantContext,
        UpdateTenantSettings $updateTenantSettings,
    ): RedirectResponse {
        $updateTenantSettings->handle($tenantContext->get(), $request->validated('name'), $request->user());

        return back()->with('status', 'Company settings updated.');
    }
}
