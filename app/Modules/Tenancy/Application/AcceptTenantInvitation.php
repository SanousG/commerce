<?php

namespace App\Modules\Tenancy\Application;

use App\Models\User;
use App\Modules\Tenancy\Domain\Enums\TenantInvitationStatus;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\TenantInvitation;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptTenantInvitation
{
    public function handle(string $token, User $user): TenantMember
    {
        $membership = DB::transaction(function () use ($token, $user): ?TenantMember {
            $invitation = TenantInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->with(['tenant', 'role'])
                ->first();

            if ($invitation === null || $invitation->status === TenantInvitationStatus::Revoked) {
                return null;
            }

            if (strtolower(trim($user->email)) !== $invitation->email) {
                throw ValidationException::withMessages(['invitation' => 'This invitation was sent to another address.']);
            }

            if ($invitation->status === TenantInvitationStatus::Accepted) {
                return TenantMember::query()
                    ->where('tenant_id', $invitation->tenant_id)
                    ->where('user_id', $user->getKey())
                    ->firstOrFail();
            }

            if ($invitation->status !== TenantInvitationStatus::Pending || $invitation->expires_at->isPast()) {
                if ($invitation->status === TenantInvitationStatus::Pending) {
                    $invitation->update(['status' => TenantInvitationStatus::Expired]);
                }

                return null;
            }

            if ($invitation->tenant->status !== TenantStatus::Active || ! $invitation->role->is_system) {
                return null;
            }

            $membership = TenantMember::query()->firstOrCreate(
                ['tenant_id' => $invitation->tenant_id, 'user_id' => $user->getKey()],
                ['role_id' => $invitation->role_id, 'status' => TenantMemberStatus::Active, 'joined_at' => now()],
            );

            $invitation->update(['status' => TenantInvitationStatus::Accepted, 'accepted_at' => now()]);

            return $membership;
        });

        if ($membership === null) {
            $this->invalid();
        }

        return $membership;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['invitation' => 'This invitation is invalid or has expired.']);
    }
}
