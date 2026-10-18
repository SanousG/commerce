<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Notifications\Application\ReconcileOperationalNotifications;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use Tests\Feature\Payments\RefundsFeatureTestCase;

class RefundNotificationLifecycleTest extends RefundsFeatureTestCase
{
    public function test_pending_refund_creates_one_notification_and_completion_resolves_it(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $store = $this->createStore($tenant);
        $this->enablePayments($tenant, $store);
        [$order] = $this->readyOrder($tenant, $store, $owner, 1);
        $this->assertDatabaseHas('admin_notifications', [
            'tenant_id' => $tenant->getKey(),
            'deduplication_key' => 'event:order-placed:'.$order->getKey(),
            'type' => NotificationType::OrderPlaced->value,
        ]);
        [$payment, $capture] = $this->capturedPayment($tenant, $store, $order, $order->grand_total, $owner);
        $this->assertDatabaseHas('admin_notifications', [
            'tenant_id' => $tenant->getKey(),
            'deduplication_key' => 'event:payment-captured:'.$capture->getKey(),
            'type' => NotificationType::PaymentCaptured->value,
        ]);

        $refund = $this->createRefund($tenant, $store, $payment, $order->grand_total, 'notify-refund', actor: $owner);
        app(ReconcileOperationalNotifications::class)->reconcile($tenant->getKey());
        $notification = AdminNotification::query()
            ->where('deduplication_key', 'condition:refund:'.$refund->getKey())->sole();
        $this->assertSame(NotificationType::RefundPending, $notification->type);
        $this->assertSame($store->getKey(), $notification->store_id);

        $this->completeRefund($tenant, $store, $refund, 'notify-refund-complete', 'REF-NOTIFY-1', $owner);
        app(ReconcileOperationalNotifications::class)->reconcile($tenant->getKey());

        $this->assertNotNull($notification->fresh()->resolved_at);
        $this->assertDatabaseHas('admin_notifications', [
            'tenant_id' => $tenant->getKey(),
            'deduplication_key' => 'event:refund-completed:'.PaymentTransaction::query()->where('refund_id', $refund->getKey())->sole()->getKey(),
            'type' => NotificationType::RefundCompleted->value,
        ]);
        $this->assertSame(1, AdminNotification::query()
            ->where('deduplication_key', 'condition:refund:'.$refund->getKey())->count());
    }
}
