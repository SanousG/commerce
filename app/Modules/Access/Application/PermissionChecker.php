<?php

namespace App\Modules\Access\Application;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Http\Request;

class PermissionChecker
{
    /** @var array<string, TenantMember|null> */
    private array $memberships = [];

    private ?Request $request = null;

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function userCan(User $user, PermissionKey|string $permission): bool
    {
        $membership = $this->currentMembership($user);

        return $membership !== null && $membership->role->permissions->contains(
            'key',
            $this->permissionValue($permission),
        );
    }

    public function can(TenantMember $membership, PermissionKey|string $permission): bool
    {
        if ($this->tenantContext->id() !== $membership->tenant_id) {
            return false;
        }

        return TenantMember::query()
            ->whereKey($membership->getKey())
            ->where('tenant_id', $this->tenantContext->id())
            ->where('user_id', $membership->user_id)
            ->where('status', TenantMemberStatus::Active)
            ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active))
            ->whereHas('role', fn ($query) => $query
                ->whereNull('tenant_id')
                ->where('is_system', true)
                ->whereHas('permissions', fn ($permissions) => $permissions
                    ->where('key', $this->permissionValue($permission))))
            ->exists();
    }

    public function currentMembership(User $user): ?TenantMember
    {
        if (! $this->tenantContext->hasTenant()) {
            return null;
        }

        $request = app(Request::class);

        if ($this->request !== $request) {
            $this->memberships = [];
            $this->request = $request;
        }

        $key = $this->tenantContext->id().':'.$user->getKey();

        if (array_key_exists($key, $this->memberships)) {
            return $this->memberships[$key];
        }

        return $this->memberships[$key] = TenantMember::query()
            ->where('tenant_id', $this->tenantContext->id())
            ->where('user_id', $user->getKey())
            ->where('status', TenantMemberStatus::Active)
            ->whereHas('tenant', fn ($query) => $query->where('status', TenantStatus::Active))
            ->whereHas('role', fn ($query) => $query
                ->whereNull('tenant_id')
                ->where('is_system', true))
            ->with(['role.permissions' => fn ($query) => $query->orderBy('key')])
            ->first();
    }

    /** @return list<string> */
    public function permissions(User $user): array
    {
        return $this->currentMembership($user)?->role->permissions
            ->pluck('key')
            ->all() ?? [];
    }

    private function permissionValue(PermissionKey|string $permission): string
    {
        return $permission instanceof PermissionKey ? $permission->value : $permission;
    }
}
