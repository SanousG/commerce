<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Domain\Models\AdminNotification;

final class UnreadNotificationCount
{
    public function forUser(string $tenantId, ?string $storeId, int $userId): int
    {
        return AdminNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('recipient_user_id', $userId)
            ->where(fn ($query) => $query->whereNull('store_id')->when($storeId !== null, fn ($query) => $query->orWhere('store_id', $storeId)))
            ->whereNull('read_at')
            ->whereNull('dismissed_at')
            ->whereNull('resolved_at')
            ->count();
    }
}
