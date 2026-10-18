<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Notifications\Application\NotificationRecipientResolver;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

class NotificationScopeAndConstraintTest extends NotificationsFeatureTestCase
{
    public function test_shared_unread_count_excludes_read_resolved_and_dismissed_and_respects_active_store(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->addMember($tenant, $other);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $this->notification($tenant, $user);
        $this->notification($tenant, $user, $storeA);
        $this->notification($tenant, $user, $storeB);
        $this->notification($tenant, $user, $storeA, ['read_at' => now()]);
        $this->notification($tenant, $user, $storeA, ['resolved_at' => now()]);
        $this->notification($tenant, $user, $storeA, ['dismissed_at' => now()]);
        $this->notification($tenant, $other, $storeA);

        $this->useTenant($user, $tenant, $storeA);
        $this->get(route('admin.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->where('unreadNotificationsCount', 2));

        $this->useTenant($user, $tenant, $storeB);
        $this->get(route('admin.notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->where('unreadNotificationsCount', 2));
    }

    public function test_recipient_resolver_uses_active_system_role_permissions_and_tenant_membership(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $suspended = User::factory()->create();
        $outsider = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $this->addMember($tenant, $admin, SystemRole::Admin);
        $this->addMember($tenant, $member, SystemRole::Member);
        $this->addMember($tenant, $suspended, SystemRole::Owner, TenantMemberStatus::Suspended);
        $this->createTenantFor($outsider);
        $resolver = app(NotificationRecipientResolver::class);

        $this->assertSame(
            collect([$owner->getKey(), $admin->getKey(), $member->getKey()])->sort()->values()->all(),
            $resolver->userIds($tenant->getKey(), [PermissionKey::InventoryView])->sort()->values()->all(),
        );
        $this->assertSame(
            collect([$owner->getKey(), $admin->getKey()])->sort()->values()->all(),
            $resolver->userIds($tenant->getKey(), [PermissionKey::RefundsManage])->sort()->values()->all(),
        );
        $this->assertSame([], $resolver->userIds($tenant->getKey(), [])->all());
    }

    public function test_database_partial_unique_index_rejects_duplicate_active_deduplication_key(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $attributes = ['deduplication_key' => 'condition:duplicate'];
        $this->notification($tenant, $user, attributes: $attributes);

        $this->expectException(QueryException::class);
        $this->notification($tenant, $user, attributes: $attributes);
    }

    public function test_database_allows_a_new_notification_after_the_previous_one_is_resolved(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $attributes = ['deduplication_key' => 'condition:resolved-repeat'];
        $first = $this->notification($tenant, $user, attributes: $attributes);
        $first->update(['resolved_at' => now()]);
        $second = $this->notification($tenant, $user, attributes: $attributes);

        $this->assertNotSame($first->getKey(), $second->getKey());
        $this->assertSame(2, AdminNotification::query()->where('deduplication_key', 'condition:resolved-repeat')->count());
    }
}
