<?php

namespace App\Modules\Notifications\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotification;

final class UnreadNotificationCount
{
    public function forUser(string $tenantId, ?string $storeId, User $user, PermissionChecker $permissions): int
    {
        $allowedTypes = collect(NotificationType::cases())
            ->filter(function (NotificationType $type) use ($user, $permissions): bool {
                $permission = NotificationRouting::permission($type);

                return $permission === null || $permissions->userCan($user, $permission);
            })
            ->map(fn (NotificationType $type): string => $type->value);

        return AdminNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('recipient_user_id', $user->getKey())
            ->whereIn('type', $allowedTypes)
            ->where(fn ($query) => $query->whereNull('store_id')->when($storeId !== null, fn ($query) => $query->orWhere('store_id', $storeId)))
            ->whereNull('read_at')
            ->whereNull('dismissed_at')
            ->whereNull('resolved_at')
            ->count();
    }
}
