<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Notifications\Application\ReconcileOperationalNotifications;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use App\Modules\Returns\Application\CompleteReturn;
use App\Modules\Returns\Application\RestockReturnItem;
use Tests\Feature\Returns\ReturnsFeatureTestCase;

class ReturnNotificationLifecycleTest extends ReturnsFeatureTestCase
{
    public function test_received_return_creates_one_notification_and_completion_resolves_it(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $store = $this->createStore($tenant);
        [$order, , , , $level] = $this->fulfilledOrder($tenant, $store, 1, actor: $owner);
        $return = $this->requestReturn($tenant, $store, $order, 1, $owner);

        $received = $this->receiveReturn($tenant, $store, $return, 1, $owner);
        app(ReconcileOperationalNotifications::class)->reconcile($tenant->getKey());
        $notification = AdminNotification::query()
            ->where('deduplication_key', 'condition:return:'.$return->getKey())->sole();
        $this->assertSame(NotificationType::ReturnAwaitingInspection, $notification->type);
        $this->assertSame($store->getKey(), $notification->store_id);

        $item = $this->inspectReturnItem($tenant, $store, $received, $return->items()->sole(), 1, actor: $owner);
        app(RestockReturnItem::class)->handle($tenant, $store, $return->fresh(), $item, $level->location, 1, $owner);
        app(CompleteReturn::class)->handle($tenant, $store, $return->fresh(), $owner);
        app(ReconcileOperationalNotifications::class)->reconcile($tenant->getKey());

        $this->assertNotNull($notification->fresh()->resolved_at);
        $this->assertSame(1, AdminNotification::query()
            ->where('deduplication_key', 'condition:return:'.$return->getKey())->count());
    }
}
