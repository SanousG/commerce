<?php

namespace App\Modules\Tenancy\Application;

use App\Mail\TenantInvitationMail;
use App\Modules\Tenancy\Domain\Enums\TenantInvitationStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ResendTenantInvitation
{
    public function handle(Tenant $tenant, string $invitationId): TenantInvitation
    {
        [$invitation, $token] = DB::transaction(function () use ($tenant, $invitationId): array {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            if ($lockedTenant->status !== TenantStatus::Active) {
                throw ValidationException::withMessages(['tenant' => 'Memberships cannot be managed while the company is inactive.']);
            }

            $invitation = $lockedTenant->invitations()->lockForUpdate()->findOrFail($invitationId);
            if ($invitation->status !== TenantInvitationStatus::Pending) {
                throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be resent.']);
            }

            $token = bin2hex(random_bytes(32));
            $invitation->update([
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(config('tenancy.invitation_expiration_hours')),
            ]);

            return [$invitation->load(['tenant', 'role']), $token];
        });

        Mail::to($invitation->email)->send(new TenantInvitationMail($invitation, $token));

        return $invitation;
    }
}
