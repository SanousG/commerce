<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Tests\Feature\Procurement\ProcurementFeatureTestCase;

abstract class NotificationsFeatureTestCase extends ProcurementFeatureTestCase
{
    protected function addMember(
        Tenant $tenant,
        User $user,
        SystemRole $role = SystemRole::Owner,
        TenantMemberStatus $status = TenantMemberStatus::Active,
    ): TenantMember {
        return TenantMember::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->system($role)->sole()->getKey(),
            'status' => $status,
            'joined_at' => $status === TenantMemberStatus::Active ? now() : null,
        ]);
    }

    protected function notification(
        Tenant $tenant,
        User $recipient,
        ?Store $store = null,
        array $attributes = [],
    ): AdminNotification {
        return AdminNotification::query()->create(array_replace([
            'tenant_id' => $tenant->getKey(),
            'store_id' => $store?->getKey(),
            'recipient_user_id' => $recipient->getKey(),
            'type' => NotificationType::LowStock,
            'severity' => NotificationSeverity::Warning,
            'title' => fake()->unique()->sentence(3),
            'message' => fake()->sentence(),
            'action_url' => '/admin/inventory',
            'deduplication_key' => 'event:test:'.fake()->unique()->uuid(),
            'metadata' => [],
        ], $attributes));
    }
}
