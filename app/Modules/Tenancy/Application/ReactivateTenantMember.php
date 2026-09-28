<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReactivateTenantMember
{
    public function handle(Tenant $tenant, string $memberId): void
    {
        DB::transaction(function () use ($tenant, $memberId): void {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            if ($lockedTenant->status !== TenantStatus::Active) {
                throw ValidationException::withMessages(['tenant' => 'Memberships cannot be managed while the company is inactive.']);
            }

            $member = $lockedTenant->members()->lockForUpdate()->findOrFail($memberId);
            $member->update(['status' => TenantMemberStatus::Active]);
        });
    }
}
