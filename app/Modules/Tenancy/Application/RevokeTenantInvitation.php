<?php

namespace App\Modules\Tenancy\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Tenancy\Domain\Enums\TenantInvitationStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeTenantInvitation
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(Tenant $tenant, string $invitationId, ?User $actor = null): void
    {
        DB::transaction(function () use ($tenant, $invitationId, $actor): void {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            if ($lockedTenant->status !== TenantStatus::Active) {
                throw ValidationException::withMessages(['tenant' => 'Memberships cannot be managed while the company is inactive.']);
            }

            $invitation = $lockedTenant->invitations()->lockForUpdate()->findOrFail($invitationId);
            if ($invitation->status !== TenantInvitationStatus::Pending) {
                throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be revoked.']);
            }

            $invitation->update(['status' => TenantInvitationStatus::Revoked]);

            $this->audit->record(
                $lockedTenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::TenantInvitationRevoked,
                AuditSubjectType::TenantInvitation,
                $invitation->getKey(),
                ['changes' => ['status' => [
                    'from' => TenantInvitationStatus::Pending,
                    'to' => TenantInvitationStatus::Revoked,
                ]]],
                actor: $actor,
                subjectLabel: $invitation->email,
            );
        });
    }
}
