<?php

namespace App\Modules\Tenancy\Application;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeTenantMemberRole
{
    public function __construct(private readonly EnsureTenantHasActiveOwner $ownerGuard) {}

    public function handle(Tenant $tenant, User $actor, string $memberId, string $roleId): TenantMember
    {
        return DB::transaction(function () use ($tenant, $actor, $memberId, $roleId): TenantMember {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $this->ensureManageable($lockedTenant);
            $member = $lockedTenant->members()->with('role')->lockForUpdate()->findOrFail($memberId);

            $role = Role::query()->system()->findOrFail($roleId);
            if ($member->status === TenantMemberStatus::Active && $role->key !== SystemRole::Owner->value) {
                $this->ownerGuard->afterRemovingOwner($lockedTenant, $member);
            }

            if ((int) $member->user_id === (int) $actor->getKey()) {
                throw ValidationException::withMessages(['member' => 'You cannot change your own role.']);
            }

            $member->update(['role_id' => $role->getKey()]);

            return $member->fresh(['user', 'role.permissions']);
        });
    }

    private function ensureManageable(Tenant $tenant): void
    {
        if ($tenant->status !== TenantStatus::Active) {
            throw ValidationException::withMessages(['tenant' => 'Memberships cannot be managed while the company is inactive.']);
        }
    }
}
