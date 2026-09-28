<?php

namespace App\Modules\Tenancy\Application;

use App\Models\User;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeactivateTenantMember
{
    public function __construct(private readonly EnsureTenantHasActiveOwner $ownerGuard) {}

    public function handle(Tenant $tenant, User $actor, string $memberId): void
    {
        DB::transaction(function () use ($tenant, $actor, $memberId): void {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            if ($lockedTenant->status !== TenantStatus::Active) {
                throw ValidationException::withMessages(['tenant' => 'Memberships cannot be managed while the company is inactive.']);
            }

            $member = $lockedTenant->members()->with('role')->lockForUpdate()->findOrFail($memberId);
            if ($member->status === TenantMemberStatus::Active) {
                $this->ownerGuard->afterRemovingOwner($lockedTenant, $member);
                if ((int) $member->user_id === (int) $actor->getKey()) {
                    throw ValidationException::withMessages(['member' => 'You cannot deactivate your own membership.']);
                }
                $member->update(['status' => TenantMemberStatus::Suspended]);
            }
        });
    }
}
