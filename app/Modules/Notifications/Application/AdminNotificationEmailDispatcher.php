<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Jobs\SendAdminNotificationEmail;

/**
 * Queues email for freshly created notifications whose recipient opted in.
 *
 * Only rows created by the current delivery are queued. Repeated deliveries of
 * the same deduplication key reuse the existing active row, so they never
 * re-queue an email.
 */
final class AdminNotificationEmailDispatcher
{
    public function __construct(private readonly NotificationPreferences $preferences) {}

    /** @param array<int, string> $notificationIdsByRecipient recipient user id => notification id */
    public function dispatchForNewRecipients(array $notificationIdsByRecipient, string $tenantId, NotificationType $type): void
    {
        foreach ($notificationIdsByRecipient as $recipientId => $notificationId) {
            if ($this->preferences->allowsEmail($tenantId, (int) $recipientId, $type)) {
                SendAdminNotificationEmail::dispatch((string) $notificationId);
            }
        }
    }
}
