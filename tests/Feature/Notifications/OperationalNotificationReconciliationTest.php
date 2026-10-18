<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Notifications\Application\ReconcileOperationalNotifications;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Notifications\Domain\Models\AdminNotification;
use App\Modules\Procurement\Application\CancelPurchaseOrder;
use App\Modules\Procurement\Application\MarkPurchaseOrderOrdered;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use Illuminate\Support\Str;

class OperationalNotificationReconciliationTest extends NotificationsFeatureTestCase
{
    public function test_low_stock_reconciliation_deduplicates_escalates_recovers_and_creates_new_after_resolution(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $variant = $this->createVariant($tenant, 'NOTIFY-STOCK');
        $variant->product()->update(['status' => ProductStatus::Active]);
        $location = InventoryLocation::query()->where('tenant_id', $tenant->getKey())->sole();
        $this->stock($tenant, $location, $variant, 3, $owner);
        $reconciler = app(ReconcileOperationalNotifications::class);

        $firstResult = $reconciler->reconcile($tenant->getKey());
        $first = AdminNotification::query()->where('deduplication_key', 'condition:stock:'.$variant->getKey())->sole();
        $this->assertSame(NotificationType::LowStock, $first->type);
        $this->assertSame(NotificationSeverity::Warning, $first->severity);
        $this->assertSame(1, $firstResult['deliveries']);

        $secondResult = $reconciler->reconcile($tenant->getKey());
        $this->assertSame(1, $secondResult['deliveries']);
        $this->assertSame(1, AdminNotification::query()->where('deduplication_key', $first->deduplication_key)->count());

        $first->update(['read_at' => now()]);
        $this->stock($tenant, $location, $variant, -3, $owner);
        $reconciler->reconcile($tenant->getKey());
        $escalated = $first->fresh();
        $this->assertSame(NotificationType::OutOfStock, $escalated->type);
        $this->assertSame(NotificationSeverity::Critical, $escalated->severity);
        $this->assertNull($escalated->read_at);

        $this->stock($tenant, $location, $variant, 10, $owner);
        $reconciler->reconcile($tenant->getKey());
        $this->assertNotNull($first->fresh()->resolved_at);

        $this->stock($tenant, $location, $variant, -7, $owner);
        $reconciler->reconcile($tenant->getKey());
        $active = AdminNotification::query()->where('deduplication_key', $first->deduplication_key)
            ->whereNull('resolved_at')->sole();
        $this->assertNotSame($first->getKey(), $active->getKey());
        $this->assertSame(2, AdminNotification::query()->where('deduplication_key', $first->deduplication_key)->count());
    }

    public function test_overdue_purchase_order_notification_resolves_when_order_becomes_terminal(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $variant = $this->createVariant($tenant);
        $supplier = $this->supplier($tenant);
        $supplierProduct = $this->supplierProduct($tenant, $supplier, $variant);
        $order = $this->draftOrder($tenant, $supplier, $supplierProduct, actor: $owner);
        $order->update([
            'expected_at' => now()->subDay(),
            'status' => PurchaseOrderStatus::Approved,
            'approved_by_user_id' => $owner->getKey(),
        ]);
        $order = app(MarkPurchaseOrderOrdered::class)->handle($tenant, $order->fresh());
        $reconciler = app(ReconcileOperationalNotifications::class);

        $reconciler->reconcile($tenant->getKey());
        $notification = AdminNotification::query()
            ->where('deduplication_key', 'condition:purchase-order:'.$order->getKey())->sole();
        $this->assertSame(NotificationType::PurchaseOrderToReceive, $notification->type);

        app(CancelPurchaseOrder::class)->handle($tenant, $order->fresh(), $owner);
        $reconciler->reconcile($tenant->getKey());
        $this->assertNotNull($notification->fresh()->resolved_at);
    }

    public function test_tenant_command_only_reconciles_requested_tenant_and_second_run_does_not_duplicate(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $tenantA = $this->createTenantFor($ownerA);
        $tenantB = $this->createTenantFor($ownerB);
        $variantA = $this->createVariant($tenantA, 'COMMAND-A');
        $variantB = $this->createVariant($tenantB, 'COMMAND-B');
        $variantA->product()->update(['status' => ProductStatus::Active]);
        $variantB->product()->update(['status' => ProductStatus::Active]);

        $this->artisan('notifications:reconcile', ['--tenant' => $tenantA->getKey()])->assertSuccessful();
        $this->assertDatabaseHas('admin_notifications', [
            'tenant_id' => $tenantA->getKey(), 'deduplication_key' => 'condition:stock:'.$variantA->getKey(),
        ]);
        $this->assertDatabaseMissing('admin_notifications', [
            'tenant_id' => $tenantB->getKey(), 'deduplication_key' => 'condition:stock:'.$variantB->getKey(),
        ]);

        $this->artisan('notifications:reconcile', ['--tenant' => $tenantA->getKey()])->assertSuccessful();
        $this->assertSame(1, AdminNotification::query()->where('tenant_id', $tenantA->getKey())
            ->where('deduplication_key', 'condition:stock:'.$variantA->getKey())->count());
    }

    public function test_command_fails_for_an_unknown_tenant(): void
    {
        $this->artisan('notifications:reconcile', ['--tenant' => (string) Str::uuid()])
            ->expectsOutput('The active tenant was not found.')
            ->assertFailed();
    }
}
