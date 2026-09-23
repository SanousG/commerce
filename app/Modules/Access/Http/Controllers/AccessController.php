<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccessController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $tenantContext,
        PermissionChecker $permissionChecker,
    ): Response {
        $membership = $permissionChecker->currentMembership($request->user());
        abort_unless($membership !== null, 403);

        $roles = Role::query()
            ->system()
            ->with(['permissions' => fn ($query) => $query->orderBy('key')])
            ->orderByRaw("case key when 'OWNER' then 1 when 'ADMIN' then 2 else 3 end")
            ->get();

        return Inertia::render('admin/access/Index', [
            'tenant' => $tenantContext->get()->only(['name']),
            'currentRole' => $membership->role->key,
            'permissions' => $membership->role->permissions->pluck('key'),
            'roles' => $roles->map(fn (Role $role) => [
                'key' => $role->key,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('key'),
            ]),
        ]);
    }
}
