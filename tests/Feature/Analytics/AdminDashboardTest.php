<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Analytics\Application\InventoryDashboardMetrics;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Orders\OrdersFeatureTestCase;

class AdminDashboardTest extends OrdersFeatureTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_empty_dashboard_returns_zero_read_models_without_null_math(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Dashboard')
                ->where('dashboard.range.key', 'today')
                ->where('dashboard.currency', 'PEN')
                ->where('dashboard.sections.sales.gross_sales', '0.0000')
                ->where('dashboard.sections.sales.orders', 0)
                ->where('dashboard.sections.sales.average_order_value', '0.0000')
                ->where('dashboard.sections.payments.net_paid', '0.0000')
                ->where('dashboard.sections.inventory.low_stock_variants', 0)
                ->where('dashboard.sections.procurement.incoming_units', 0));
    }

    public function test_sales_use_order_snapshots_store_timezone_store_scope_and_store_currency(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        [$confirmed] = $this->readyOrder($tenant, $storeA, $user);
        [$completed] = $this->readyOrder($tenant, $storeA, $user);
        [$cancelled] = $this->readyOrder($tenant, $storeA, $user);
        [$otherCurrency] = $this->readyOrder($tenant, $storeA, $user);
        [$otherStore] = $this->readyOrder($tenant, $storeB, $user);

        $this->setOrder($confirmed, '100.0000', OrderStatus::Confirmed, '2026-09-27 05:00:00 UTC');
        $this->setOrder($completed, '50.0000', OrderStatus::Completed, '2026-09-28 04:59:59 UTC');
        $this->setOrder($cancelled, '80.0000', OrderStatus::Cancelled, '2026-09-27 12:00:00 UTC');
        $this->setOrder($otherCurrency, '700.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');
        $otherCurrency->update(['currency' => 'USD']);
        $this->setOrder($otherStore, '900.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');
        $completed->update(['fulfillment_status' => OrderFulfillmentStatus::Fulfilled]);
        $this->useDashboardContext($user, $tenant->getKey(), $storeA->getKey());

        $this->get(route('admin.dashboard', ['range' => 'today']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.sections.sales.gross_sales', '150.0000')
                ->where('dashboard.sections.sales.orders', 2)
                ->where('dashboard.sections.sales.average_order_value', '75.0000')
                ->where('dashboard.sections.sales.orders_by_status.CONFIRMED', 1)
                ->where('dashboard.sections.sales.orders_by_status.COMPLETED', 1)
                ->where('dashboard.sections.sales.orders_by_status.CANCELLED', 1)
                ->where('dashboard.sections.sales.excluded_currency_orders', 1)
                ->has('dashboard.sections.sales.top_products', 2)
                ->where('dashboard.sections.fulfillment.orders_by_status.UNFULFILLED', 1)
                ->where('dashboard.sections.fulfillment.orders_by_status.FULFILLED', 1));
    }

    public function test_member_never_receives_unauthorized_payment_dataset(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, SystemRole::Member);
        $store = $this->createStore($tenant);
        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('dashboard.sections.sales')
            ->missing('dashboard.sections.payments'));
    }

    public function test_inventory_aggregates_variants_across_active_locations_and_isolates_tenants(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant, ['name' => 'Low stock product']);
        $variant = $product->variants()->sole();
        $main = $this->stock($tenant, $variant, 2);
        $second = InventoryLocation::create([
            'tenant_id' => $tenant->getKey(), 'name' => 'Second', 'code' => 'SECOND-'.Str::upper(Str::random(5)),
            'status' => InventoryLocationStatus::Active, 'is_default' => false,
        ]);
        $inactive = InventoryLocation::create([
            'tenant_id' => $tenant->getKey(), 'name' => 'Inactive', 'code' => 'INACTIVE-'.Str::upper(Str::random(5)),
            'status' => InventoryLocationStatus::Inactive, 'is_default' => false,
        ]);
        InventoryLevel::create(['tenant_id' => $tenant->getKey(), 'inventory_location_id' => $second->getKey(), 'product_variant_id' => $variant->getKey(), 'on_hand' => 2, 'reserved' => 0]);
        InventoryLevel::create(['tenant_id' => $tenant->getKey(), 'inventory_location_id' => $inactive->getKey(), 'product_variant_id' => $variant->getKey(), 'on_hand' => 100, 'reserved' => 0]);
        $main->update(['reserved' => 2]);

        $foreign = $this->createTenantFor(User::factory()->create());
        $foreignProduct = $this->createProduct($foreign);
        $this->stock($foreign, $foreignProduct->variants()->sole(), 0);

        $metrics = app(InventoryDashboardMetrics::class)->build($tenant->getKey());

        $this->assertSame(1, $metrics['low_stock_variants']);
        $this->assertSame(0, $metrics['out_of_stock_variants']);
        $this->assertCount(1, $metrics['items']);
        $this->assertSame(2, $metrics['items'][0]['available']);
        $this->assertSame(4, $metrics['items'][0]['on_hand']);
        $this->assertSame(2, $metrics['items'][0]['reserved']);
    }

    public function test_invalid_range_is_rejected(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'drop table orders']))->assertSessionHasErrors('range');
    }

    private function useDashboardContext(User $user, string $tenantId, string $storeId): void
    {
        $this->actingAs($user)->withSession([
            TenantContext::SESSION_KEY => $tenantId,
            StoreContext::SESSION_KEY => $storeId,
        ]);
    }

    private function setOrder(Order $order, string $total, OrderStatus $status, string $placedAt): void
    {
        $order->update([
            'status' => $status,
            'subtotal_before_discounts' => $total,
            'discount_total' => '0.0000',
            'subtotal' => $total,
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'taxable_amount' => $total,
            'exempt_amount' => '0.0000',
            'unaffected_amount' => '0.0000',
            'grand_total' => $total,
            'placed_at' => $placedAt,
            'cancelled_at' => $status === OrderStatus::Cancelled ? $placedAt : null,
        ]);
    }
}
