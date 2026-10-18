<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class NotificationManager
{
    public function __construct(
        private readonly NotificationPreferences $preferences,
        private readonly AdminNotificationEmailDispatcher $emails,
    ) {}

    /** @param iterable<int|string> $recipientUserIds
     * @return Collection<int, AdminNotification>
     */
    public function deliverEvent(
        string $tenantId,
        ?string $storeId,
        iterable $recipientUserIds,
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
        return $this->deliver($tenantId, $storeId, $recipientUserIds, $this->deduplicationKey('event', $eventKey), $type, $severity, $title, $message, $actionUrl, $metadata, $subjectType, $subjectId, true);
    }

    /** @param iterable<int|string> $recipientUserIds
     * @return Collection<int, AdminNotification>
     */
    public function deliverCondition(
        string $tenantId,
        ?string $storeId,
        iterable $recipientUserIds,
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
        return $this->deliver($tenantId, $storeId, $recipientUserIds, $this->deduplicationKey('condition', $conditionKey), $type, $severity, $title, $message, $actionUrl, $metadata, $subjectType, $subjectId, false);
    }

    public function resolveKey(string $tenantId, ?string $storeId, string $deduplicationKey): int
    {
        return $this->activeScope($tenantId, $storeId)
            ->where('deduplication_key', $deduplicationKey)
            ->update(['resolved_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  list<string>  $preserveKeys
     * @param  list<int>  $preserveRecipientUserIds
     */
    public function resolvePrefix(
        string $tenantId,
        ?string $storeId,
        string $prefix,
        array $preserveKeys = [],
        array $preserveRecipientUserIds = [],
    ): int {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix);

        return $this->activeScope($tenantId, $storeId)
            ->where('deduplication_key', 'like', $escaped.'%')
            ->when($preserveKeys !== [] && $preserveRecipientUserIds !== [], fn ($query) => $query->where(fn ($query) => $query
                ->whereNotIn('deduplication_key', $preserveKeys)
                ->orWhereNotIn('recipient_user_id', $preserveRecipientUserIds)))
            ->update(['resolved_at' => now(), 'updated_at' => now()]);
    }

    /** @param iterable<int|string> $recipientUserIds
     * @return Collection<int, AdminNotification>
     */
    private function deliver(
        string $tenantId,
        ?string $storeId,
        iterable $recipientUserIds,
        string $deduplicationKey,
        NotificationType $type,
        NotificationSeverity $severity,
        string $title,
        string $message,
        ?string $actionUrl,
        array $metadata,
        ?string $subjectType,
        ?string $subjectId,
        bool $permanentIdempotency,
    ): Collection {
        $title = trim($title);
        $message = trim($message);
        $deduplicationKey = trim($deduplicationKey);
        $actionUrl = $this->safeActionUrl($actionUrl);

        if ($title === '' || mb_strlen($title) > 180 || $message === '' || $deduplicationKey === '' || mb_strlen($deduplicationKey) > 255) {
            throw new InvalidArgumentException('Notification title, message, or deduplication key is invalid.');
        }

        if ($storeId !== null && ! DB::table('stores')->where('id', $storeId)->where('tenant_id', $tenantId)->exists()) {
            throw new InvalidArgumentException('Notification store does not belong to the tenant.');
        }

        $requestedIds = collect($recipientUserIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $recipientIds = DB::table('tenant_members')
            ->join('tenants', 'tenants.id', '=', 'tenant_members.tenant_id')
            ->where('tenant_members.tenant_id', $tenantId)
            ->whereIn('tenant_members.user_id', $requestedIds)
            ->where('tenant_members.status', TenantMemberStatus::Active->value)
            ->where('tenants.status', TenantStatus::Active->value)
            ->pluck('tenant_members.user_id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        // Recipients that muted this type are dropped before any row is written.
        $recipientIds = $this->preferences->filterRecipients($tenantId, $recipientIds, $type);

        if ($recipientIds->isEmpty()) {
            return collect();
        }

        $existingQuery = $permanentIdempotency
            ? AdminNotification::query()
                ->where('tenant_id', $tenantId)
                ->when($storeId === null, fn ($query) => $query->whereNull('store_id'), fn ($query) => $query->where('store_id', $storeId))
            : $this->activeScope($tenantId, $storeId);
        $existingRecipientIds = $existingQuery
            ->whereIn('recipient_user_id', $recipientIds)
            ->where('deduplication_key', $deduplicationKey)
            ->pluck('recipient_user_id')
            ->map(fn ($id): int => (int) $id);

        $notificationIds = $recipientIds->mapWithKeys(fn (int $recipientId): array => [$recipientId => (string) Str::uuid7()]);

        $now = now();
        $newRecipientIds = $recipientIds->diff($existingRecipientIds)->values();
        DB::table('admin_notifications')->insertOrIgnore($newRecipientIds->map(fn (int $recipientId): array => [
            'id' => $notificationIds[$recipientId],
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'recipient_user_id' => $recipientId,
            'type' => $type->value,
            'severity' => $severity->value,
            'title' => $title,
            'message' => $message,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action_url' => $actionUrl,
            'deduplication_key' => $deduplicationKey,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $notifications = ($permanentIdempotency
            ? AdminNotification::query()
                ->where('tenant_id', $tenantId)
                ->when($storeId === null, fn ($query) => $query->whereNull('store_id'), fn ($query) => $query->where('store_id', $storeId))
            : $this->activeScope($tenantId, $storeId))
            ->whereIn('recipient_user_id', $recipientIds)
            ->where('deduplication_key', $deduplicationKey)
            ->get();

        foreach ($notifications as $notification) {
            $escalated = $severity->rank() > $notification->severity->rank();
            $notification->forceFill([
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'action_url' => $actionUrl,
                'metadata' => $metadata,
                'read_at' => $escalated ? null : $notification->read_at,
            ])->save();
        }

        $newNotificationIds = $newRecipientIds
            ->mapWithKeys(fn (int $recipientId): array => [$recipientId => $notificationIds[$recipientId]])
            ->all();

        if ($newNotificationIds !== []) {
            DB::afterCommit(function () use ($newNotificationIds, $tenantId, $type): void {
                try {
                    $this->emails->dispatchForNewRecipients($newNotificationIds, $tenantId, $type);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        }

        return $notifications;
    }

    private function activeScope(string $tenantId, ?string $storeId)
    {
        return AdminNotification::query()
            ->where('tenant_id', $tenantId)
            ->when($storeId === null, fn ($query) => $query->whereNull('store_id'), fn ($query) => $query->where('store_id', $storeId))
            ->whereNull('dismissed_at')
            ->whereNull('resolved_at');
    }

    private function deduplicationKey(string $namespace, string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            throw new InvalidArgumentException('Notification event and condition keys cannot be empty.');
        }

        return $namespace.':'.$key;
    }

    private function safeActionUrl(?string $actionUrl): ?string
    {
        if ($actionUrl === null) {
            return null;
        }

        $actionUrl = trim($actionUrl);
        $parts = parse_url($actionUrl);
        $path = is_array($parts) ? ($parts['path'] ?? '') : '';
        $boundary = $path === '/admin' || str_starts_with($path, '/admin/');

        if (! is_array($parts) || ! $boundary || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['port']) || isset($parts['fragment'])
            || str_contains($actionUrl, '\\') || preg_match('/[\x00-\x1F\x7F]/', $actionUrl) === 1) {
            throw new InvalidArgumentException('Notification action URLs must be internal /admin paths.');
        }

        return $actionUrl;
    }
}
