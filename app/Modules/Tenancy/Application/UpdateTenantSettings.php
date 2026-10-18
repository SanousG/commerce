<?php

namespace App\Modules\Tenancy\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class UpdateTenantSettings
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(Tenant $tenant, string $name, ?User $actor = null): Tenant
    {
        return DB::transaction(function () use ($tenant, $name, $actor): Tenant {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $name = trim($name);
            if ($lockedTenant->name === $name) {
                return $lockedTenant;
            }

            $from = $lockedTenant->name;
            $lockedTenant->update(['name' => $name]);
            $this->audit->record(
                $lockedTenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::TenantUpdated,
                AuditSubjectType::Tenant,
                $lockedTenant->getKey(),
                ['changes' => ['name' => ['from' => $from, 'to' => $name]]],
                actor: $actor,
                subjectLabel: $lockedTenant->name,
            );

            return $lockedTenant;
        });
    }
}
