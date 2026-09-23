<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly StoreContext $storeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $tenantId = $request->session()->get(TenantContext::SESSION_KEY);

        if ($tenantId === null) {
            $request->session()->forget(StoreContext::SESSION_KEY);

            $memberships = $request->user()->tenantMemberships()
                ->where('status', TenantMemberStatus::Active)
                ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active))
                ->with(['tenant', 'role'])
                ->limit(2)
                ->get();

            if ($memberships->isEmpty()) {
                return to_route('admin.tenants.create');
            }

            if ($memberships->count() > 1) {
                return to_route('admin.tenants.index');
            }

            $membership = $memberships->first();
            $request->session()->put(TenantContext::SESSION_KEY, $membership->tenant_id);
        } else {
            $membership = $request->user()->tenantMemberships()
                ->where('tenant_id', $tenantId)
                ->where('status', TenantMemberStatus::Active)
                ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active))
                ->with(['tenant', 'role'])
                ->first();

            if ($membership === null) {
                $request->session()->forget(TenantContext::SESSION_KEY);
                $request->session()->forget(StoreContext::SESSION_KEY);

                return to_route('admin.tenants.index');
            }
        }

        $this->tenantContext->set($membership->tenant);

        try {
            return $next($request);
        } finally {
            $this->tenantContext->clear();
        }
    }
}
