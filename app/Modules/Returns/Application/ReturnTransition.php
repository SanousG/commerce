<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Notifications\Application\BestEffortNotificationManager;
use App\Modules\Notifications\Application\NotificationRecipientResolver;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReturnTransition
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly BestEffortNotificationManager $notifications,
        private readonly NotificationRecipientResolver $recipients,
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
        if ($status === ReturnStatus::Received) {
            DB::afterCommit(function () use ($tenant, $store, $return): void {
                try {
                    $recipients = $this->recipients->userIds($tenant->getKey(), [
                        PermissionKey::ReturnsInspect,
                        PermissionKey::ReturnsManage,
                    ]);
                    $this->notifications->deliverCondition(
                        $tenant->getKey(), $store->getKey(), $recipients, 'return:'.$return->getKey(),
                        NotificationType::ReturnAwaitingInspection, NotificationSeverity::Warning,
                        'Return awaiting inspection', 'A received return is ready for inspection.',
                        '/admin/returns/'.$return->getKey(), ['return_id' => $return->getKey()],
                        AuditSubjectType::ReturnRequest->value, $return->getKey(),
                    );
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

            return;
        }

        if (! in_array($status, [ReturnStatus::Completed, ReturnStatus::Cancelled], true)) {
            return;
        }

        DB::afterCommit(function () use ($tenant, $store, $return): void {
            try {
                DB::table('admin_notifications')
                    ->where('tenant_id', $tenant->getKey())
                    ->where('store_id', $store->getKey())
                    ->whereIn('deduplication_key', [
                        'condition:return:'.$return->getKey(),
                        'event:return:'.$return->getKey(),
                    ])
                    ->whereNull('resolved_at')
                    ->whereNull('dismissed_at')
                    ->update(['resolved_at' => now(), 'updated_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
