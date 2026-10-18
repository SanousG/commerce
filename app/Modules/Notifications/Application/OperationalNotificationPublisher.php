<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use Illuminate\Support\Collection;

/**
 * Small application-level bridge used by business services.
 *
 * Call it inside the transaction that owns the transition. In-app rows then
 * roll back with the business mutation, while NotificationManager schedules
 * optional email only after commit.
 */
final class OperationalNotificationPublisher
{
    public function __construct(
        private readonly NotificationRecipientResolver $recipients,
        private readonly NotificationManager $notifications,
    ) {}

    /** @param iterable<PermissionKey|string> $permissions */
    public function event(
        string $tenantId,
        ?string $storeId,
        iterable $permissions,
        string $eventKey,
        NotificationType $type,
        NotificationSeverity $severity,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $metadata = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): Collection {
        return $this->notifications->deliverEvent(
            $tenantId,
            $storeId,
            $this->recipients->userIds($tenantId, $permissions),
            $eventKey,
            $type,
            $severity,
            $title,
            $message,
            $actionUrl,
            $metadata,
            $subjectType,
            $subjectId,
        );
    }

    /** @param iterable<PermissionKey|string> $permissions */
    public function condition(
        string $tenantId,
        ?string $storeId,
        iterable $permissions,
        string $conditionKey,
        NotificationType $type,
        NotificationSeverity $severity,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $metadata = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): Collection {
        return $this->notifications->deliverCondition(
            $tenantId,
            $storeId,
            $this->recipients->userIds($tenantId, $permissions),
            $conditionKey,
            $type,
            $severity,
            $title,
            $message,
            $actionUrl,
            $metadata,
            $subjectType,
            $subjectId,
        );
    }

    public function resolveCondition(string $tenantId, ?string $storeId, string $conditionKey): int
    {
        return $this->notifications->resolveKey($tenantId, $storeId, 'condition:'.$conditionKey);
    }
}
