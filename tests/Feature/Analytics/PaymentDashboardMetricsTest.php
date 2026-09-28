<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Analytics\Application\PaymentDashboardMetrics;
use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Application\OrderPaymentCalculator;
use Carbon\CarbonImmutable;
use Tests\Feature\Payments\RefundsFeatureTestCase;

class PaymentDashboardMetricsTest extends RefundsFeatureTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_aggregate_ledger_metrics_match_per_order_calculator(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        $this->enablePayments($tenant, $store);
        [$first] = $this->readyOrder($tenant, $store, $actor);
        [$second] = $this->readyOrder($tenant, $store, $actor);
        $this->setTotal($first, '100.0000');
        $this->setTotal($second, '50.0000');

        [$firstPayment] = $this->capturedPayment($tenant, $store, $first->fresh(), '100.0000', $actor, 'capture-100');
        $refund = $this->createRefund($tenant, $store, $firstPayment, '20.0000', 'refund-20', actor: $actor);
        $this->completeRefund($tenant, $store, $refund, 'complete-20', 'REF-20', $actor);
        $this->capturedPayment($tenant, $store, $second->fresh(), '20.0000', $actor, 'capture-20');

        $range = DashboardDateRange::make(DashboardRange::Today, $store->timezone);
        $metrics = app(PaymentDashboardMetrics::class)->build(
            $tenant->getKey(), $store->getKey(), $store->currency, $range, true,
        );
        $calculator = app(OrderPaymentCalculator::class);

        $this->assertSame('80.0000', $calculator->calculate($first->fresh())->netPaid);
        $this->assertSame('20.0000', $calculator->calculate($second->fresh())->netPaid);
        $this->assertSame('100.0000', $metrics['net_paid']);
        $this->assertSame('50.0000', $metrics['pending_amount']);
        $this->assertSame('20.0000', $metrics['refunded_amount']);
        $this->assertSame(0, $metrics['pending_refunds']);
    }

    private function setTotal(Order $order, string $total): void
    {
        $order->update([
            'subtotal_before_discounts' => $total,
            'discount_total' => '0.0000',
            'subtotal' => $total,
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => $total,
            'taxable_amount' => $total,
            'exempt_amount' => '0.0000',
            'unaffected_amount' => '0.0000',
        ]);
    }
}
