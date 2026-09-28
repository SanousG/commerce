<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\Enums\TenantInvitationStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeTenantInvitation
{
    public function handle(Tenant $tenant, string $invitationId): void
    {
        DB::transaction(function () use ($tenant, $invitationId): void {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            if ($lockedTenant->status !== TenantStatus::Active) {
                throw ValidationException::withMessages(['tenant' => 'Memberships cannot be managed while the company is inactive.']);
            }

            $invitation = $lockedTenant->invitations()->lockForUpdate()->findOrFail($invitationId);
            if ($invitation->status !== TenantInvitationStatus::Pending) {
                throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be revoked.']);
            }

            $invitation->update(['status' => TenantInvitationStatus::Revoked]);
        });
    }
}
