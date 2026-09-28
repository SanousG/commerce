<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Validation\ValidationException;

class EnsureTenantHasActiveOwner
{
    public function afterRemovingOwner(Tenant $tenant, TenantMember $membership): void
    {
        if ($membership->role->key !== SystemRole::Owner->value) {
            return;
        }

        $hasAnotherOwner = $tenant->members()
            ->whereKeyNot($membership->getKey())
            ->where('status', TenantMemberStatus::Active)
            ->whereHas('role', fn ($query) => $query->system(SystemRole::Owner))
            ->lockForUpdate()
            ->exists();

        if (! $hasAnotherOwner) {
            throw ValidationException::withMessages(['member' => 'A company must always have at least one active owner.']);
        }
    }
}
