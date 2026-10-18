<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class NotificationRecipientResolver
{
    /**
     * Resolves the same active system-role permission grants as PermissionChecker,
     * without relying on the request-scoped TenantContext.
     *
     * @param  iterable<PermissionKey|string>  $permissions
     * @return Collection<int, int>
     */
    public function userIds(string $tenantId, iterable $permissions): Collection
    {
        $keys = collect($permissions)
            ->map(fn (PermissionKey|string $permission): string => $permission instanceof PermissionKey ? $permission->value : $permission)
            ->filter(fn (string $permission): bool => $permission !== '')
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return collect();
        }

        return DB::table('tenant_members')
            ->join('tenants', 'tenants.id', '=', 'tenant_members.tenant_id')
            ->join('roles', 'roles.id', '=', 'tenant_members.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('tenant_members.tenant_id', $tenantId)
            ->where('tenant_members.status', TenantMemberStatus::Active->value)
            ->where('tenants.status', TenantStatus::Active->value)
            ->whereNull('roles.tenant_id')
            ->where('roles.is_system', true)
            ->whereIn('permissions.key', $keys)
            ->distinct()
            ->orderBy('tenant_members.user_id')
            ->pluck('tenant_members.user_id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }
}
