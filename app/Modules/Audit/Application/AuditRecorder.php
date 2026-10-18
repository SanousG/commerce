<?php

namespace App\Modules\Audit\Application;

use App\Models\User;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Audit\Domain\Models\AuditEntry;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AuditRecorder
{
    public function __construct(
        private readonly AuditMetadata $metadata,
        private readonly Request $request,
    ) {}

    /**
     * Appends immediately on the current connection, so an enclosing caller transaction owns atomicity.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Tenant $tenant,
        AuditActorType $actorType,
        AuditAction $action,
        AuditSubjectType $subjectType,
        ?string $subjectId,
        array $metadata = [],
        ?Store $store = null,
        ?User $actor = null,
        ?string $correlationId = null,
        ?string $idempotencyKey = null,
        ?CarbonInterface $occurredAt = null,
        ?string $subjectLabel = null,
    ): AuditEntry {
        $subjectId = $subjectId === null ? null : trim($subjectId);
        if ($subjectId === '' || ($subjectId !== null && mb_strlen($subjectId) > 255)) {
            throw new InvalidArgumentException('The audit subject ID must contain at most 255 characters.');
        }

        $subjectLabel = $subjectLabel === null ? null : trim($subjectLabel);
        if ($subjectLabel === '' || ($subjectLabel !== null && mb_strlen($subjectLabel) > 255)) {
            throw new InvalidArgumentException('The audit subject label must contain at most 255 characters.');
        }

        if ($store !== null && $store->tenant_id !== $tenant->getKey()) {
            throw new InvalidArgumentException('The audit store must belong to the tenant.');
        }

        if ($actorType === AuditActorType::System && $actor !== null) {
            throw new InvalidArgumentException('A system audit entry may not have a user actor.');
        }

        if ($actorType === AuditActorType::User && ($actor === null || ! $tenant->members()
            ->where('user_id', $actor->getKey())->where('status', TenantMemberStatus::Active)->exists())) {
            throw new InvalidArgumentException('A user audit actor must be an active tenant member.');
        }

        $correlationId ??= (string) Str::uuid7();
        if (! Str::isUuid($correlationId)) {
            throw new InvalidArgumentException('The audit correlation ID must be a UUID.');
        }

        $idempotencyKey = $idempotencyKey === null ? null : trim($idempotencyKey);
        if ($idempotencyKey === '' || ($idempotencyKey !== null && mb_strlen($idempotencyKey) > 100)) {
            throw new InvalidArgumentException('The audit idempotency key must contain 1 to 100 characters.');
        }

        return AuditEntry::query()->create([
            'tenant_id' => $tenant->getKey(),
            'store_id' => $store?->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'actor_type' => $actorType,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'metadata' => $this->metadata->sanitize($metadata),
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit($this->request->userAgent() ?? '', 1024, ''),
            'occurred_at' => $occurredAt ?? now(),
            'correlation_id' => $correlationId,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
        ]);
    }
}
