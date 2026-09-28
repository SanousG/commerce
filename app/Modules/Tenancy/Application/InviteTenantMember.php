<?php

namespace App\Modules\Tenancy\Application;

use App\Mail\TenantInvitationMail;
use App\Models\User;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Tenancy\Domain\Enums\TenantInvitationStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InviteTenantMember
{
    public function handle(Tenant $tenant, User $actor, string $email, string $roleId): TenantInvitation
    {
        $email = strtolower(trim($email));
        [$invitation, $token] = DB::transaction(function () use ($tenant, $actor, $email, $roleId): array {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $this->ensureActive($lockedTenant);
            $role = Role::query()->system()->findOrFail($roleId);

            if ($lockedTenant->members()->whereHas('user', fn ($query) => $query->where('email', $email))->exists()) {
                throw ValidationException::withMessages(['email' => 'This email is already a member of the company.']);
            }

            $lockedTenant->invitations()
                ->where('email', $email)
                ->where('status', TenantInvitationStatus::Pending)
                ->where('expires_at', '<', now())
                ->update(['status' => TenantInvitationStatus::Expired, 'updated_at' => now()]);

            if ($lockedTenant->invitations()->where('email', $email)->where('status', TenantInvitationStatus::Pending)->exists()) {
                throw ValidationException::withMessages(['email' => 'A pending invitation already exists for this email.']);
            }

            $token = bin2hex(random_bytes(32));
            $invitation = $lockedTenant->invitations()->create([
                'email' => $email,
                'role_id' => $role->getKey(),
                'token_hash' => hash('sha256', $token),
                'status' => TenantInvitationStatus::Pending,
                'invited_by_user_id' => $actor->getKey(),
                'expires_at' => now()->addHours(config('tenancy.invitation_expiration_hours')),
            ]);

            return [$invitation->load(['tenant', 'role']), $token];
        });

        Mail::to($invitation->email)->send(new TenantInvitationMail($invitation, $token));

        return $invitation;
    }

    private function ensureActive(Tenant $tenant): void
    {
        if ($tenant->status !== TenantStatus::Active) {
            throw ValidationException::withMessages(['tenant' => 'Memberships cannot be managed while the company is inactive.']);
        }
    }
}
