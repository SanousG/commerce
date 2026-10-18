<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\RefundsFeatureTestCase;

class DashboardRbacTest extends RefundsFeatureTestCase
{
    use InteractsWithDashboard;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_member_never_receives_financial_sections_or_comparison_metrics(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, SystemRole::Member);
        $store = $this->createStore($tenant);

        [$order] = $this->readyOrder($tenant, $store, $user);
        $this->placeOrder($order, '100.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dashboard.sections.sales')
                ->missing('dashboard.sections.payments')
                ->missing('dashboard.sections.profitability')
                ->has('dashboard.comparison.metrics.gross_sales')
                ->has('dashboard.comparison.metrics.orders')
                ->has('dashboard.comparison.metrics.average_order_value')
                ->missing('dashboard.comparison.metrics.captured_amount')
                ->missing('dashboard.comparison.metrics.net_paid')
                ->missing('dashboard.comparison.metrics.refunded_amount'));
    }

    public function test_recent_order_payment_and_fulfillment_columns_follow_permissions(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, SystemRole::Member);
        $store = $this->createStore($tenant);

        [$order] = $this->readyOrder($tenant, $store, $user);
        $this->placeOrder($order, '120.0000', OrderStatus::Confirmed, '2026-09-27 12:00:00 UTC');

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dashboard.sections.recent_orders', 1)
                ->has('dashboard.sections.recent_orders.0.fulfillment_status')
                ->missing('dashboard.sections.recent_orders.0.payment_status'));
    }

    public function test_owner_receives_financial_comparison_metrics(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        $this->useDashboardContext($user, $tenant->getKey(), $store->getKey());

        $this->get(route('admin.dashboard', ['range' => 'today']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('dashboard.sections.payments')
                ->has('dashboard.comparison.metrics.captured_amount')
                ->has('dashboard.comparison.metrics.net_paid')
                ->has('dashboard.comparison.metrics.refunded_amount')
                ->has('dashboard.sections.recent_orders'));
    }
}
