<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\RefundsFeatureTestCase;

class DashboardIsolationTest extends RefundsFeatureTestCase
{
    use InteractsWithDashboard;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_recent_orders_are_isolated_between_stores_of_the_same_tenant(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);

        [$orderA] = $this->readyOrder($tenant, $storeA, $user, quantity: 1);
        $this->placeOrder($orderA, '11.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');
        [$orderB] = $this->readyOrder($tenant, $storeB, $user, quantity: 1);
        $this->placeOrder($orderB, '22.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');

        $this->useDashboardContext($user, $tenant->getKey(), $storeA->getKey());

        $this->get(route('admin.dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.sections.sales.orders', 1)
                ->where('dashboard.sections.sales.gross_sales', '11.0000')
                ->has('dashboard.sections.recent_orders', 1)
                ->where('dashboard.sections.recent_orders.0.number', $orderA->order_number));

        $this->useDashboardContext($user, $tenant->getKey(), $storeB->getKey());

        $this->get(route('admin.dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.sections.sales.gross_sales', '22.0000')
                ->has('dashboard.sections.recent_orders', 1)
                ->where('dashboard.sections.recent_orders.0.number', $orderB->order_number));
    }

    public function test_dashboard_reads_never_mutate_commercial_records(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->enablePayments($tenant, $store);

        [$order] = $this->readyOrder($tenant, $store, $user);
        $this->placeOrder($order, '100.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');
        [$payment] = $this->capturedPayment($tenant, $store, $order->fresh(), '100.0000', $user, 'isolation-capture');
        $refund = $this->createRefund($tenant, $store, $payment, '10.0000', 'isolation-refund', actor: $user);
        $this->completeRefund($tenant, $store, $refund, 'isolation-complete', 'REF-ISO', $user);

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $before = $this->snapshot();
        $this->get(route('admin.dashboard', ['range' => '30d']))->assertOk();
        $after = $this->snapshot();

        $this->assertSame($before, $after);
    }

    public function test_dashboard_query_count_is_stable_as_order_volume_grows(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        foreach (range(1, 2) as $index) {
            [$order] = $this->readyOrder($tenant, $store, $user, quantity: 1);
            $this->placeOrder($order, '10.0000', OrderStatus::Confirmed, CarbonImmutable::now()->subHours($index)->toDateTimeString());
        }

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());
        $this->requestQueryCount();

        $baseline = $this->requestQueryCount();

        foreach (range(3, 12) as $index) {
            [$order] = $this->readyOrder($tenant, $store, $user, quantity: 1);
            $this->placeOrder($order, '10.0000', OrderStatus::Confirmed, CarbonImmutable::now()->subHours($index)->toDateTimeString());
        }

        $withMoreOrders = $this->requestQueryCount();

        $this->assertSame($baseline, $withMoreOrders);
    }

    private function requestQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('admin.dashboard', ['range' => '30d']))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** @return array<string, string> */
    private function snapshot(): array
    {
        $tables = [
            'orders', 'order_items', 'inventory_levels', 'inventory_reservations',
            'payments', 'payment_transactions', 'refunds', 'fulfillments',
        ];

        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }
}
