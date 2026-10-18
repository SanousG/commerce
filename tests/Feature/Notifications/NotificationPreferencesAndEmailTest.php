<?php

namespace Tests\Feature\Notifications;

use App\Mail\TenantInvitationMail;
use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Notifications\Application\AdminNotificationEmailDispatcher;
use App\Modules\Notifications\Application\NotificationManager;
use App\Modules\Notifications\Application\NotificationPreferences;
use App\Modules\Notifications\Application\NotificationRecipientResolver;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Jobs\SendAdminNotificationEmail;
use App\Modules\Notifications\Mail\AdminNotificationMail;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class NotificationPreferencesAndEmailTest extends NotificationsFeatureTestCase
{
    public function test_preferences_are_scoped_to_current_user_and_tenant_and_mandatory_types_cannot_be_muted(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $storeA = $this->createStore($tenantA);
        $storeB = $this->createStore($tenantB);

        $this->useTenant($user, $tenantA, $storeA);
        $this->patch(route('admin.notifications.preferences.update'), [
            'email_enabled' => true,
            'muted_types' => [NotificationType::LowStock->value],
        ])->assertRedirect();

        $this->assertDatabaseHas('admin_notification_preferences', [
            'tenant_id' => $tenantA->getKey(), 'user_id' => $user->getKey(), 'email_enabled' => true,
        ]);
        $this->assertDatabaseMissing('admin_notification_preferences', [
            'tenant_id' => $tenantB->getKey(), 'user_id' => $user->getKey(),
        ]);

        $this->useTenant($user, $tenantB, $storeB);
        $this->get(route('admin.notifications.preferences.show'))->assertOk();
        $this->patch(route('admin.notifications.preferences.update'), [
            'email_enabled' => false,
            'muted_types' => [NotificationType::FiscalRejected->value],
        ])->assertSessionHasErrors('muted_types');
    }

    public function test_muted_type_suppresses_in_app_delivery_but_mandatory_type_bypasses_preferences(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $preferences = app(NotificationPreferences::class);
        $preferences->update($tenant->getKey(), $user->getKey(), [
            'email_enabled' => false,
            'muted_types' => [NotificationType::LowStock->value],
        ]);
        $manager = app(NotificationManager::class);

        $lowStock = $manager->deliverCondition(
            $tenant->getKey(), null, [$user->getKey()], 'stock:test',
            NotificationType::LowStock, NotificationSeverity::Warning,
            'Low stock', 'A variant has low stock.',
        );
        $fiscal = $manager->deliverCondition(
            $tenant->getKey(), null, [$user->getKey()], 'fiscal:test',
            NotificationType::FiscalRejected, NotificationSeverity::Critical,
            'Fiscal document rejected', 'A fiscal document requires attention.',
        );

        $this->assertCount(0, $lowStock);
        $this->assertCount(1, $fiscal);
    }

    public function test_event_idempotency_survives_dismissal_and_distinguishes_two_real_transitions(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $manager = app(NotificationManager::class);
        $deliver = fn (string $transition) => $manager->deliverEvent(
            $tenant->getKey(), null, [$user->getKey()], 'payment:'.$transition,
            NotificationType::PaymentCaptured, NotificationSeverity::Info,
            'Payment captured', 'A payment capture was confirmed.',
        );

        $first = $deliver('capture-1')->sole();
        $first->update(['dismissed_at' => now()]);
        $retry = $deliver('capture-1')->sole();
        $deliver('capture-2');

        $this->assertSame($first->getKey(), $retry->getKey());
        $this->assertDatabaseCount('admin_notifications', 2);
    }

    public function test_notification_written_in_business_transaction_rolls_back_with_it(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);

        try {
            DB::transaction(function () use ($tenant, $user): void {
                app(NotificationManager::class)->deliverEvent(
                    $tenant->getKey(), null, [$user->getKey()], 'rollback-test',
                    NotificationType::OrderPlaced, NotificationSeverity::Info,
                    'New order', 'There is a new order to review.',
                );

                throw new RuntimeException('roll back');
            });
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertDatabaseMissing('admin_notifications', [
            'tenant_id' => $tenant->getKey(), 'deduplication_key' => 'event:rollback-test',
        ]);
    }

    public function test_email_job_rechecks_permission_membership_and_preferences_and_is_idempotent(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        app(NotificationPreferences::class)->update($tenant->getKey(), $user->getKey(), [
            'email_enabled' => true, 'muted_types' => [],
        ]);
        $notification = $this->notification($tenant, $user, attributes: [
            'type' => NotificationType::RefundCompleted,
            'title' => 'Refund completed',
            'message' => 'A refund was completed.',
        ]);
        $job = new SendAdminNotificationEmail($notification->getKey());

        $job->handle(app(NotificationRecipientResolver::class), app(NotificationPreferences::class));
        $job->handle(app(NotificationRecipientResolver::class), app(NotificationPreferences::class));

        Mail::assertSent(AdminNotificationMail::class, 1);
        $this->assertNotNull($notification->fresh()->emailed_at);
    }

    public function test_email_job_skips_a_revoked_recipient(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);

        $other = User::factory()->create();
        $this->addMember($tenant, $other, SystemRole::Admin, TenantMemberStatus::Suspended);
        app(NotificationPreferences::class)->update($tenant->getKey(), $other->getKey(), [
            'email_enabled' => true, 'muted_types' => [],
        ]);
        $revoked = $this->notification($tenant, $other, attributes: [
            'type' => NotificationType::RefundCompleted,
        ]);
        (new SendAdminNotificationEmail($revoked->getKey()))->handle(
            app(NotificationRecipientResolver::class),
            app(NotificationPreferences::class),
        );

        Mail::assertNothingSent();
        $this->assertNull($revoked->fresh()->emailed_at);
        Mail::assertNotSent(TenantInvitationMail::class);
    }

    public function test_email_dispatch_uses_an_after_commit_queued_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        app(NotificationPreferences::class)->update($tenant->getKey(), $user->getKey(), [
            'email_enabled' => true, 'muted_types' => [],
        ]);
        $notification = $this->notification($tenant, $user);

        app(AdminNotificationEmailDispatcher::class)->dispatchForNewRecipients(
            [$user->getKey() => $notification->getKey()], $tenant->getKey(), NotificationType::LowStock,
        );

        Queue::assertPushed(SendAdminNotificationEmail::class, fn (SendAdminNotificationEmail $job): bool => $job->notificationId === $notification->getKey() && $job->afterCommit
        );
    }

    public function test_mail_transport_failure_releases_claim_without_removing_business_notification(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        app(NotificationPreferences::class)->update($tenant->getKey(), $user->getKey(), [
            'email_enabled' => true, 'muted_types' => [],
        ]);
        $notification = $this->notification($tenant, $user);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('provider unavailable'));

        try {
            (new SendAdminNotificationEmail($notification->getKey()))->handle(
                app(NotificationRecipientResolver::class),
                app(NotificationPreferences::class),
            );
            $this->fail('The transport exception should be retried by the worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('provider unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('admin_notifications', ['id' => $notification->getKey()]);
        $this->assertNull($notification->fresh()->emailed_at);
    }
}
