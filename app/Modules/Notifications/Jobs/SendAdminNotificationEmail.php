<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\User;
use App\Modules\Notifications\Application\NotificationPreferences;
use App\Modules\Notifications\Application\NotificationRecipientResolver;
use App\Modules\Notifications\Application\NotificationRouting;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use App\Modules\Notifications\Mail\AdminNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers one admin notification by email.
 *
 * The row is claimed with `emailed_at` before sending so concurrent workers do
 * not duplicate a message. The claim is released when the transport throws, so
 * a retry after an observed failure re-attempts delivery. This is not exactly
 * once: a process crash after the claim but before transport can lose an email,
 * while an ambiguous provider exception after acceptance can cause a retry and
 * duplicate. External providers expose no portable idempotency key here.
 */
class SendAdminNotificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $notificationId)
    {
        $this->afterCommit();
    }

    public function handle(NotificationRecipientResolver $permissions, NotificationPreferences $preferences): void
    {
        $notification = AdminNotification::query()->find($this->notificationId);

        if ($notification === null || $notification->emailed_at !== null) {
            return;
        }

        $tenantId = $notification->tenant_id;
        $userId = (int) $notification->recipient_user_id;
        $type = $notification->type;

        // Preferences and grants are re-evaluated at delivery time; the values
        // captured when the event happened are not trusted.
        if (! $preferences->allowsEmail($tenantId, $userId, $type)) {
            return;
        }

        $permission = NotificationRouting::permission($type);
        if ($permission !== null && ! $permissions->userHasPermission($tenantId, $userId, [$permission])) {
            return;
        }

        $user = User::query()->find($userId);
        if ($user === null || trim((string) $user->email) === '') {
            return;
        }

        $claimed = AdminNotification::query()->whereKey($notification->getKey())
            ->whereNull('emailed_at')
            ->update(['emailed_at' => now(), 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        try {
            Mail::to($user->email)->send(new AdminNotificationMail($notification));
        } catch (Throwable $exception) {
            AdminNotification::query()->whereKey($notification->getKey())
                ->update(['emailed_at' => null, 'updated_at' => now()]);

            throw $exception;
        }
    }
}
