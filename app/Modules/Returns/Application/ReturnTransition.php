<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Notifications\Application\OperationalNotificationPublisher;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Validation\ValidationException;

class ReturnTransition
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OperationalNotificationPublisher $notifications,
    ) {}

    /** @param list<ReturnStatus> $from */
    public function locked(
        Tenant $tenant,
        Store $store,
        ReturnRequest $return,
        array $from,
        ReturnStatus $to,
        ?User $actor = null,
        ?string $reason = null,
        array $attributes = [],
    ): ReturnRequest {
        $locked = ReturnRequest::query()->where('tenant_id', $tenant->getKey())
            ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($return->getKey());
        if ($locked->status === $to) {
            return $locked;
        }
        if (! in_array($locked->status, $from, true)) {
            throw ValidationException::withMessages(['return' => "The return cannot transition to {$to->value}."]);
        }
        $previous = $locked->status;
        $locked->update(['status' => $to, ...$attributes]);
        $locked->history()->create([
            'tenant_id' => $tenant->getKey(), 'from_status' => $previous, 'to_status' => $to,
            'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
        ]);
        if ($to === ReturnStatus::Approved) {
            $this->audit->record(
                tenant: $tenant, actorType: $actor === null ? AuditActorType::System : AuditActorType::User,
                action: AuditAction::ReturnApproved, subjectType: AuditSubjectType::ReturnRequest,
                subjectId: $locked->getKey(),
                metadata: [
                    'order_id' => $locked->order_id,
                    'quantity' => (int) $locked->items()->sum('requested_quantity'),
                ],
                store: $store, actor: $actor,
                subjectLabel: substr((string) $locked->getKey(), 0, 8),
            );
        }
        $this->afterCommitNotifications($tenant, $store, $locked, $to);

        return $locked;
    }

    private function afterCommitNotifications(Tenant $tenant, Store $store, ReturnRequest $return, ReturnStatus $status): void
    {
        if ($status === ReturnStatus::Approved) {
            $this->notifications->event(
                $tenant->getKey(), $store->getKey(), [PermissionKey::ReturnsManage],
                'return-approved:'.$return->getKey(),
                NotificationType::ReturnApproved, NotificationSeverity::Info,
                'Return approved', 'A return was approved and is ready to be received.',
                '/admin/returns/'.$return->getKey(), ['return_id' => $return->getKey()],
                AuditSubjectType::ReturnRequest->value, (string) $return->getKey(),
            );

            return;
        }

        if ($status === ReturnStatus::Received) {
            // Received returns are conditions rather than immutable events: the
            // row remains active until inspection is completed or cancelled.
            $this->notifications->condition(
                $tenant->getKey(), $store->getKey(), [PermissionKey::ReturnsInspect, PermissionKey::ReturnsManage],
                'return:'.$return->getKey(),
                NotificationType::ReturnAwaitingInspection, NotificationSeverity::Warning,
                'Return awaiting inspection', 'A received return is ready for inspection.',
                '/admin/returns/'.$return->getKey(), ['return_id' => $return->getKey()],
                AuditSubjectType::ReturnRequest->value, (string) $return->getKey(),
            );

            return;
        }

        if (! in_array($status, [ReturnStatus::Completed, ReturnStatus::Cancelled], true)) {
            return;
        }

        $this->notifications->resolveCondition(
            $tenant->getKey(), $store->getKey(), 'return:'.$return->getKey(),
        );
    }
}
