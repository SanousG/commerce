<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotificationPreference;
use Illuminate\Support\Collection;

/**
 * Per-user, per-tenant delivery preferences.
 *
 * Preferences never disable the audit ledger and never suppress mandatory
 * administration/security types. A missing row means the defaults: no email and
 * no muted types.
 */
final class NotificationPreferences
{
    public function forUser(string $tenantId, int $userId): AdminNotificationPreference
    {
        return AdminNotificationPreference::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            ['email_enabled' => false, 'muted_types' => []],
        );
    }

    /** @param array{email_enabled: bool, muted_types: list<string>} $attributes */
    public function update(string $tenantId, int $userId, array $attributes): AdminNotificationPreference
    {
        $preference = $this->forUser($tenantId, $userId);
        $preference->fill([
            'email_enabled' => $attributes['email_enabled'],
            'muted_types' => collect($attributes['muted_types'])->unique()->values()->all(),
        ])->save();

        return $preference->refresh();
    }

    public function isMuted(string $tenantId, int $userId, NotificationType $type): bool
    {
        if (NotificationRouting::isMandatory($type)) {
            return false;
        }

        $muted = AdminNotificationPreference::query()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->value('muted_types');

        return is_array($muted) && in_array($type->value, $muted, true);
    }

    public function allowsEmail(string $tenantId, int $userId, NotificationType $type): bool
    {
        if ($this->isMuted($tenantId, $userId, $type)) {
            return false;
        }

        return (bool) AdminNotificationPreference::query()
            ->where('tenant_id', $tenantId)->where('user_id', $userId)
            ->value('email_enabled');
    }

    /**
     * Drops recipients that muted this type. Mandatory types bypass preferences.
     *
     * @param  iterable<int|string>  $userIds
     * @return Collection<int, int>
     */
    public function filterRecipients(string $tenantId, iterable $userIds, NotificationType $type): Collection
    {
        $ids = collect($userIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty() || NotificationRouting::isMandatory($type)) {
            return $ids;
        }

        $muted = AdminNotificationPreference::query()
            ->where('tenant_id', $tenantId)->whereIn('user_id', $ids)
            ->whereJsonContains('muted_types', $type->value)
            ->pluck('user_id')->map(fn ($id): int => (int) $id);

        return $ids->diff($muted)->values();
    }
}
