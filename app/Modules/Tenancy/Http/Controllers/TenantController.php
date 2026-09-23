<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\CreateTenant;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Http\Requests\StoreTenantRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $memberships = $request->user()->tenantMemberships()
            ->where('status', TenantMemberStatus::Active)
            ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active))
            ->with(['tenant', 'role'])
            ->get()
            ->sortBy(fn ($membership) => $membership->tenant->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        if ($memberships->isEmpty()) {
            return to_route('admin.tenants.create');
        }

        return Inertia::render('admin/tenants/Index', [
            'tenants' => $memberships->map(fn ($membership) => [
                'id' => $membership->tenant->getKey(),
                'name' => $membership->tenant->name,
                'slug' => $membership->tenant->slug,
                'role' => $membership->role->key,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/tenants/Create');
    }

    public function store(StoreTenantRequest $request, CreateTenant $createTenant): RedirectResponse
    {
        $tenant = $createTenant->handle($request->user(), $request->validated('name'));

        $request->session()->put(TenantContext::SESSION_KEY, $tenant->getKey());
        $request->session()->forget(StoreContext::SESSION_KEY);

        return to_route('admin.dashboard');
    }

    public function select(Request $request, string $tenant): RedirectResponse
    {
        $membership = $request->user()->tenantMemberships()
            ->where('tenant_id', $tenant)
            ->where('status', TenantMemberStatus::Active)
            ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active))
            ->first();

        abort_unless($membership !== null, 403);

        $request->session()->put(TenantContext::SESSION_KEY, $membership->tenant_id);
        $request->session()->forget(StoreContext::SESSION_KEY);

        return to_route('admin.dashboard');
    }
}
