<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class NotificationHttpTest extends NotificationsFeatureTestCase
{
    public function test_index_is_inertia_paginated_and_only_contains_recipient_notifications_visible_in_active_store(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->addMember($tenant, $other);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $tenantWide = $this->notification($tenant, $user, attributes: ['title' => 'Tenant wide']);
        $inStore = $this->notification($tenant, $user, $storeA, ['title' => 'Store A']);
        $this->notification($tenant, $user, $storeB, ['title' => 'Store B']);
        $this->notification($tenant, $other, $storeA, ['title' => 'Other recipient']);
        $this->useTenant($user, $tenant, $storeA);

        $this->get(route('admin.notifications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/notifications/Index')
            ->where('notifications.per_page', 25)
            ->has('notifications.data', 2)
            ->where('notifications.data', fn (Collection $rows): bool => $rows->pluck('id')->sort()->values()->all()
                === collect([$tenantWide->getKey(), $inStore->getKey()])->sort()->values()->all())
            ->where('filters', ['unread' => false, 'active' => false, 'resolved' => false, 'severity' => '', 'type' => ''])
            ->has('types')->has('severities'));
    }

    #[DataProvider('filterCases')]
    public function test_index_filters_notifications(array $query, array $expectedKeys): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $notifications = [];
        $notifications['target'] = $this->notification($tenant, $user, $store, [
            'type' => NotificationType::OutOfStock,
            'severity' => NotificationSeverity::Critical,
            'title' => 'Target active unread',
        ]);
        $notifications['read'] = $this->notification($tenant, $user, $store, ['read_at' => now(), 'title' => 'Read']);
        $notifications['resolved'] = $this->notification($tenant, $user, $store, ['resolved_at' => now(), 'title' => 'Resolved']);
        $notifications['dismissed'] = $this->notification($tenant, $user, $store, ['dismissed_at' => now(), 'title' => 'Dismissed']);
        $this->useTenant($user, $tenant, $store);

        $expectedIds = collect($expectedKeys)->map(fn (string $key) => $notifications[$key]->getKey())->sort()->values()->all();

        $this->get(route('admin.notifications.index', $query))->assertInertia(fn (Assert $page) => $page
            ->has('notifications.data', count($expectedIds))
            ->where('notifications.data', fn (Collection $rows): bool => $rows->pluck('id')->sort()->values()->all() === $expectedIds));
    }

    public function test_index_rejects_invalid_filter_values(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->useTenant($user, $tenant, $this->createStore($tenant));
        $this->get(route('admin.notifications.index', ['severity' => 'INVALID']))->assertSessionHasErrors('severity');
    }

    public static function filterCases(): array
    {
        return [
            'unread' => [['unread' => 1], ['target', 'resolved', 'dismissed']],
            'active' => [['active' => 1], ['target', 'read']],
            'resolved' => [['resolved' => 1], ['resolved']],
            'severity' => [['severity' => NotificationSeverity::Critical->value], ['target']],
            'type' => [['type' => NotificationType::OutOfStock->value], ['target']],
        ];
    }

    public function test_read_and_dismiss_return_404_for_another_recipient_and_another_tenant(): void
    {
        $user = User::factory()->create();
        $sameTenantUser = User::factory()->create();
        $foreignUser = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->addMember($tenant, $sameTenantUser);
        $store = $this->createStore($tenant);
        $foreignTenant = $this->createTenantFor($foreignUser);
        $foreignStore = $this->createStore($foreignTenant);
        $sameTenant = $this->notification($tenant, $sameTenantUser, $store);
        $foreign = $this->notification($foreignTenant, $foreignUser, $foreignStore);
        $this->useTenant($user, $tenant, $store);

        foreach ([$sameTenant, $foreign] as $notification) {
            $this->post(route('admin.notifications.read', $notification))->assertNotFound();
            $this->post(route('admin.notifications.dismiss', $notification))->assertNotFound();
        }

        $this->assertNull($sameTenant->fresh()->read_at);
        $this->assertNull($sameTenant->fresh()->dismissed_at);
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertNull($foreign->fresh()->dismissed_at);
    }

    public function test_read_is_idempotent_and_preserves_the_first_read_timestamp(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $notification = $this->notification($tenant, $user, $store);
        $this->useTenant($user, $tenant, $store);
        $firstRead = CarbonImmutable::parse('2026-09-29 10:00:00 UTC');
        $secondRead = $firstRead->addHour();

        $this->travelTo($firstRead);
        $this->post(route('admin.notifications.read', $notification))->assertRedirect();
        $this->assertTrue($notification->fresh()->read_at->equalTo($firstRead));

        $this->travelTo($secondRead);
        $this->post(route('admin.notifications.read', $notification))->assertRedirect();
        $this->assertTrue($notification->fresh()->read_at->equalTo($firstRead));
    }

    public function test_read_all_only_marks_the_current_recipients_visible_store_scope(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignUser = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->addMember($tenant, $other);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $foreignTenant = $this->createTenantFor($foreignUser);
        $foreignStore = $this->createStore($foreignTenant);
        $tenantWide = $this->notification($tenant, $user);
        $visible = $this->notification($tenant, $user, $storeA);
        $otherStore = $this->notification($tenant, $user, $storeB);
        $otherRecipient = $this->notification($tenant, $other, $storeA);
        $foreign = $this->notification($foreignTenant, $foreignUser, $foreignStore);
        $this->useTenant($user, $tenant, $storeA);

        $this->post(route('admin.notifications.read-all'))->assertRedirect();

        $this->assertNotNull($tenantWide->fresh()->read_at);
        $this->assertNotNull($visible->fresh()->read_at);
        $this->assertNull($otherStore->fresh()->read_at);
        $this->assertNull($otherRecipient->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_dismiss_sets_a_timestamp_without_deleting_the_notification(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $notification = $this->notification($tenant, $user, $store);
        $this->useTenant($user, $tenant, $store);

        $this->post(route('admin.notifications.dismiss', $notification))->assertRedirect();

        $this->assertDatabaseHas('admin_notifications', ['id' => $notification->getKey()]);
        $this->assertNotNull($notification->fresh()->dismissed_at);
    }
}
