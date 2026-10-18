<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Analytics\Application\RecentOrdersDashboardMetrics;
use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Tests\Feature\Payments\RefundsFeatureTestCase;

class RecentOrdersDashboardMetricsTest extends RefundsFeatureTestCase
{
    use InteractsWithDashboard;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_recent_orders_are_ordered_limited_and_store_and_tenant_scoped(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $otherStore = $this->createStore($tenant);

        $orders = [];
        for ($index = 0; $index < 11; $index++) {
            [$order] = $this->readyOrder($tenant, $store, $user, quantity: 1);
            $this->placeOrder(
                $order,
                '10.0000',
                OrderStatus::Confirmed,
                CarbonImmutable::now()->subHours($index)->toDateTimeString(),
            );
            $orders[] = $order->fresh();
        }

        [$otherStoreOrder] = $this->readyOrder($tenant, $otherStore, $user, quantity: 1);
        $this->placeOrder($otherStoreOrder, '10.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');

        [$foreignOrder] = $this->readyOrder($tenant, $otherStore, $user, quantity: 1);
        $this->placeOrder($foreignOrder, '10.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');

        $range = DashboardDateRange::make(DashboardRange::Today, $store->timezone);
        $rows = app(RecentOrdersDashboardMetrics::class)->build(
            $tenant->getKey(), $store->getKey(), $store->currency, $range, false, false,
        );

        $this->assertCount(10, $rows);
        $this->assertSame($orders[0]->order_number, $rows[0]['number']);
        $this->assertSame($orders[9]->order_number, $rows[9]['number']);
        $this->assertNotContains($orders[10]->order_number, array_column($rows, 'number'));
        $this->assertNotContains($otherStoreOrder->order_number, array_column($rows, 'number'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $rows[0]['placed_at']);
    }

    public function test_recent_orders_exclude_records_outside_the_selected_range(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        [$inside] = $this->readyOrder($tenant, $store, $user, quantity: 1);
        $this->placeOrder($inside, '10.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');
        [$outside] = $this->readyOrder($tenant, $store, $user, quantity: 1);
        $this->placeOrder($outside, '10.0000', OrderStatus::Confirmed, '2026-09-20 12:00:00 UTC');

        $rows = app(RecentOrdersDashboardMetrics::class)->build(
            $tenant->getKey(), $store->getKey(), $store->currency,
            DashboardDateRange::make(DashboardRange::Today, $store->timezone), false, false,
        );

        $this->assertSame([$inside->order_number], array_column($rows, 'number'));
    }

    public function test_cross_tenant_orders_are_never_returned(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        $foreignUser = User::factory()->create();
        $foreignTenant = $this->createTenantFor($foreignUser);
        $foreignStore = $this->createStore($foreignTenant);
        [$foreignOrder] = $this->readyOrder($foreignTenant, $foreignStore, $foreignUser, quantity: 1);
        $this->placeOrder($foreignOrder, '999.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');

        $rows = app(RecentOrdersDashboardMetrics::class)->build(
            $tenant->getKey(), $store->getKey(), $store->currency,
            DashboardDateRange::make(DashboardRange::Today, $store->timezone), true, true,
        );

        $this->assertSame([], $rows);
    }

    public function test_permission_flags_control_financial_status_columns(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        [$order] = $this->readyOrder($tenant, $store, $user, quantity: 1);
        $this->placeOrder($order, '10.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');

        $metrics = app(RecentOrdersDashboardMetrics::class);
        $range = DashboardDateRange::make(DashboardRange::Today, $store->timezone);

        $restricted = $metrics->build($tenant->getKey(), $store->getKey(), $store->currency, $range, false, false);
        $this->assertArrayNotHasKey('payment_status', $restricted[0]);
        $this->assertArrayNotHasKey('fulfillment_status', $restricted[0]);

        $allowed = $metrics->build($tenant->getKey(), $store->getKey(), $store->currency, $range, true, true);
        $this->assertArrayHasKey('payment_status', $allowed[0]);
        $this->assertArrayHasKey('fulfillment_status', $allowed[0]);
    }
}
