<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\RefundsFeatureTestCase;

class DashboardComparisonTest extends RefundsFeatureTestCase
{
    use InteractsWithDashboard;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_sales_comparison_uses_previous_equal_length_window(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        [$currentA] = $this->readyOrder($tenant, $store, $user);
        [$currentB] = $this->readyOrder($tenant, $store, $user);
        [$previous] = $this->readyOrder($tenant, $store, $user);

        $this->placeOrder($currentA, '100.0000', OrderStatus::Confirmed, '2026-09-22 12:00:00 UTC');
        $this->placeOrder($currentB, '50.0000', OrderStatus::Confirmed, '2026-09-25 12:00:00 UTC');
        $this->placeOrder($previous, '60.0000', OrderStatus::Confirmed, '2026-09-16 12:00:00 UTC');

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => '7d']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.comparison.same_length', true)
                ->where('dashboard.comparison.previous_range.local_end_inclusive', '2026-09-20')
                ->where('dashboard.comparison.metrics.gross_sales.current', '150.0000')
                ->where('dashboard.comparison.metrics.gross_sales.previous', '60.0000')
                ->where('dashboard.comparison.metrics.gross_sales.delta', '90.0000')
                ->where('dashboard.comparison.metrics.gross_sales.percent', '150.00')
                ->where('dashboard.comparison.metrics.orders.current', 2)
                ->where('dashboard.comparison.metrics.orders.previous', 1)
                ->where('dashboard.comparison.metrics.orders.percent', '100.00')
                ->where('dashboard.comparison.metrics.average_order_value.current', '75.0000')
                ->where('dashboard.comparison.metrics.average_order_value.previous', '60.0000')
                ->where('dashboard.comparison.metrics.average_order_value.percent', '25.00'));
    }

    public function test_zero_previous_baseline_withholds_percentage(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        [$order] = $this->readyOrder($tenant, $store, $user);
        $this->placeOrder($order, '100.0000', OrderStatus::Confirmed, '2026-09-25 12:00:00 UTC');

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => '7d']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.comparison.metrics.gross_sales.current', '100.0000')
                ->where('dashboard.comparison.metrics.gross_sales.previous', '0.0000')
                ->where('dashboard.comparison.metrics.gross_sales.percent', null)
                ->where('dashboard.comparison.metrics.orders.previous', 0)
                ->where('dashboard.comparison.metrics.orders.percent', null));
    }

    public function test_partial_captures_and_uncaptured_payments_are_distinguished(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->enablePayments($tenant, $store);

        [$order] = $this->readyOrder($tenant, $store, $user);
        $this->placeOrder($order, '100.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');
        $this->capturedPayment($tenant, $store, $order->fresh(), '40.0000', $user, 'dashboard-capture-40');
        $this->manualPayment($tenant, $store, $order->fresh(), '10.0000', $user);

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.sections.payments.captured_amount', '40.0000')
                ->where('dashboard.sections.payments.net_paid', '40.0000')
                ->where('dashboard.sections.payments.pending_amount', '60.0000')
                ->where('dashboard.comparison.metrics.captured_amount.current', '40.0000')
                ->where('dashboard.comparison.metrics.captured_amount.previous', '0.0000'));
    }

    public function test_refunds_reduce_net_collections_without_double_counting(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->enablePayments($tenant, $store);

        [$order] = $this->readyOrder($tenant, $store, $user);
        $this->placeOrder($order, '100.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');
        [$payment] = $this->capturedPayment($tenant, $store, $order->fresh(), '100.0000', $user, 'dashboard-capture-100');
        $completed = $this->createRefund($tenant, $store, $payment, '20.0000', 'dashboard-refund-20', actor: $user);
        $this->completeRefund($tenant, $store, $completed, 'dashboard-complete-20', 'REF-DASH-20', $user);
        $this->createRefund($tenant, $store, $payment, '5.0000', 'dashboard-refund-pending', actor: $user);

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.sections.payments.captured_amount', '100.0000')
                ->where('dashboard.sections.payments.net_paid', '80.0000')
                ->where('dashboard.sections.payments.refunded_amount', '20.0000')
                ->where('dashboard.sections.payments.pending_refunds', 1)
                ->where('dashboard.sections.payments.refunds_by_status.COMPLETED', 1)
                ->where('dashboard.sections.payments.refunds_by_status.PENDING', 1)
                ->where('dashboard.comparison.metrics.net_paid.current', '80.0000')
                ->where('dashboard.comparison.metrics.refunded_amount.current', '20.0000'));
    }

    public function test_custom_range_includes_full_store_days_and_compares_equal_length_window(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        [$beforeStart] = $this->readyOrder($tenant, $store, $user);
        [$atStart] = $this->readyOrder($tenant, $store, $user);
        [$atEnd] = $this->readyOrder($tenant, $store, $user);
        [$afterEnd] = $this->readyOrder($tenant, $store, $user);
        [$previous] = $this->readyOrder($tenant, $store, $user);

        $this->placeOrder($beforeStart, '10.0000', OrderStatus::Confirmed, '2026-09-01 04:59:59 UTC');
        $this->placeOrder($atStart, '20.0000', OrderStatus::Confirmed, '2026-09-01 05:00:00 UTC');
        $this->placeOrder($atEnd, '30.0000', OrderStatus::Confirmed, '2026-09-15 04:59:59 UTC');
        $this->placeOrder($afterEnd, '40.0000', OrderStatus::Confirmed, '2026-09-16 05:00:00 UTC');
        $this->placeOrder($previous, '5.0000', OrderStatus::Confirmed, '2026-08-20 12:00:00 UTC');

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-15']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('dashboard.range.key', 'custom')
                ->where('dashboard.range.local_end_inclusive', '2026-09-15')
                ->where('dashboard.sections.sales.gross_sales', '50.0000')
                ->where('dashboard.sections.sales.orders', 2)
                ->where('dashboard.comparison.previous_range.local_start', '2026-08-17T00:00:00-05:00')
                ->where('dashboard.comparison.metrics.gross_sales.current', '50.0000')
                ->where('dashboard.comparison.metrics.gross_sales.previous', '15.0000')
                ->where('dashboard.comparison.metrics.gross_sales.percent', '233.33'));
    }

    public function test_custom_range_requires_both_bounds(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'custom']))
            ->assertSessionHasErrors(['from', 'to']);
    }

    public function test_custom_range_rejects_reversed_bounds(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'custom', 'from' => '2026-09-15', 'to' => '2026-09-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_custom_range_rejects_oversized_and_malformed_windows(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'custom', 'from' => '2020-01-01', 'to' => '2026-01-01']))
            ->assertSessionHasErrors('to');

        $this->get(route('admin.dashboard', ['range' => 'custom', 'from' => '09/01/2026', 'to' => '2026-09-15']))
            ->assertSessionHasErrors('from');
    }
}
