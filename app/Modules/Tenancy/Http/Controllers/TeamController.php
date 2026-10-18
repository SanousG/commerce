<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Tenancy\Application\ChangeTenantMemberRole;
use App\Modules\Tenancy\Application\DeactivateTenantMember;
use App\Modules\Tenancy\Application\ReactivateTenantMember;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantInvitationStatus;
use App\Modules\Tenancy\Http\Requests\ChangeTenantMemberRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): Response
    {
        $tenant = $tenantContext->get();
        $members = $tenant->members()->with(['user', 'role.permissions'])->orderBy('created_at')->get();
        $roles = Role::query()->system()->with(['permissions' => fn ($query) => $query->orderBy('key')])
            ->orderByRaw("case key when 'OWNER' then 1 when 'ADMIN' then 2 else 3 end")->get();

        return Inertia::render('admin/settings/Team', [
            'tenant' => $tenant->only(['name']),
            'currentUserId' => $request->user()->getKey(),
            'canManage' => $request->user()->can('members.manage'),
            'members' => $members->map(fn ($member) => [
                'id' => $member->getKey(),
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role_id' => $member->role_id,
                'role' => $member->role->key,
                'status' => $member->status->value,
                'joined_at' => $member->joined_at?->toIso8601String() ?? $member->created_at->toIso8601String(),
                'permissions' => $member->role->permissions->pluck('key'),
            ]),
            'invitations' => $tenant->invitations()
                ->where('status', TenantInvitationStatus::Pending)
                ->with('role')->orderByDesc('created_at')->get()->map(fn ($invitation) => [
                    'id' => $invitation->getKey(),
                    'email' => $invitation->email,
                    'role' => $invitation->role->key,
                    'created_at' => $invitation->created_at->toIso8601String(),
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                    'expired' => $invitation->expires_at->isPast(),
                ]),
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->getKey(),
                'key' => $role->key,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('key'),
            ]),
        ]);
    }

    public function changeRole(
        ChangeTenantMemberRoleRequest $request,
        TenantContext $tenantContext,
        ChangeTenantMemberRole $changeRole,
        string $member,
    ): RedirectResponse {
        $changeRole->handle($tenantContext->get(), $request->user(), $member, $request->validated('role_id'));

        return back()->with('status', 'Member role updated.');
    }

    public function deactivate(
        Request $request,
        TenantContext $tenantContext,
        DeactivateTenantMember $deactivate,
        string $member,
    ): RedirectResponse {
        $deactivate->handle($tenantContext->get(), $request->user(), $member);

        return back()->with('status', 'Member deactivated.');
    }

    public function reactivate(
        Request $request,
        TenantContext $tenantContext,
        ReactivateTenantMember $reactivate,
        string $member,
    ): RedirectResponse {
        $reactivate->handle($tenantContext->get(), $member, $request->user());

        return back()->with('status', 'Member reactivated.');
    }
}
