<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Audit\Domain\Models\AuditEntry;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Carbon\CarbonInterface;
use Tests\Feature\AdminSettings\AdminSettingsFeatureTestCase;

abstract class AuditFeatureTestCase extends AdminSettingsFeatureTestCase
{
    protected function audit(
        Tenant $tenant,
        AuditAction $action,
        AuditSubjectType $subjectType = AuditSubjectType::Tenant,
        ?User $actor = null,
        ?Store $store = null,
        ?CarbonInterface $occurredAt = null,
        array $metadata = [],
        ?string $subjectId = null,
    ): AuditEntry {
        return app(AuditRecorder::class)->record(
            tenant: $tenant,
            actorType: $actor === null ? AuditActorType::System : AuditActorType::User,
            action: $action,
            subjectType: $subjectType,
            subjectId: $subjectId ?? $tenant->getKey(),
            metadata: $metadata,
            store: $store,
            actor: $actor,
            occurredAt: $occurredAt,
        );
    }
}
