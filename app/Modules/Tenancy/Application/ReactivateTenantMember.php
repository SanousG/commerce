<?php

namespace App\Modules\Tenancy\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReactivateTenantMember
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(Tenant $tenant, string $memberId, ?User $actor = null): void
    {
        DB::transaction(function () use ($tenant, $memberId, $actor): void {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            if ($lockedTenant->status !== TenantStatus::Active) {
                throw ValidationException::withMessages(['tenant' => 'Memberships cannot be managed while the company is inactive.']);
            }

            $member = $lockedTenant->members()->lockForUpdate()->findOrFail($memberId);
            if ($member->status === TenantMemberStatus::Active) {
                return;
            }

            $fromStatus = $member->status;
            $member->update(['status' => TenantMemberStatus::Active]);
            $this->audit->record(
                $lockedTenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::TenantMemberReactivated,
                AuditSubjectType::TenantMember,
                $member->getKey(),
                ['changes' => ['status' => [
                    'from' => $fromStatus,
                    'to' => TenantMemberStatus::Active,
                ]]],
                actor: $actor,
                subjectLabel: $member->user()->value('email'),
            );
        });
    }
}
