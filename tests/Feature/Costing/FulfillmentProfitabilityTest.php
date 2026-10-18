<?php

namespace Tests\Feature\Costing;

use App\Models\User;
use App\Modules\Analytics\Application\ProfitabilityDashboardMetrics;
use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use App\Modules\Costing\Application\OrderProfitability;
use App\Modules\Costing\Domain\Enums\InventoryCostMovementType;
use App\Modules\Costing\Domain\Models\InventoryCostMovement;
use App\Modules\Costing\Domain\Models\InventoryValuation;
use App\Modules\Fulfillment\Application\CompleteFulfillment;
use App\Modules\Fulfillment\Application\StartFulfillment;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Fulfillment\FulfillmentFeatureTestCase;

class FulfillmentProfitabilityTest extends FulfillmentFeatureTestCase
{
    public function test_fulfillment_freezes_cogs_and_replay_is_idempotent(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $actor, 5);
        $this->makeCod($tenant, $store, $order);
        $fulfillment = $this->complete($tenant, $this->fulfillment($tenant, $order, 5, actor: $actor), $actor);

        $item = $fulfillment->items->sole();
        $this->assertSame(['10.000000', '50.0000', $store->currency], [
            $item->unit_cost_snapshot, $item->cogs_amount, $item->cost_currency,
        ]);
        $this->assertSame(15, $level->fresh()->on_hand);
        $this->assertSame('10.000000', InventoryValuation::query()->sole()->average_unit_cost);
        $movement = InventoryCostMovement::query()->where('movement_type', InventoryCostMovementType::FulfillmentCogs)->sole();
        $this->assertSame('-50.0000', $movement->value_delta);

        app(CompleteFulfillment::class)->handle($tenant, $fulfillment->fresh(), $actor);
        $this->assertSame(1, InventoryCostMovement::query()->where('movement_type', InventoryCostMovementType::FulfillmentCogs)->count());
    }

    public function test_new_fulfillment_with_uninitialized_cost_fails_atomically(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 2);
        $this->makeCod($tenant, $store, $order);
        InventoryValuation::query()->update(['average_unit_cost' => null, 'currency' => null]);
        $fulfillment = app(StartFulfillment::class)->handle($tenant, $this->fulfillment($tenant, $order, 2));
        $before = [$level->fresh()->on_hand, $level->fresh()->reserved];

        try {
            app(CompleteFulfillment::class)->handle($tenant, $fulfillment);
            $this->fail('An uncosted fulfillment was completed.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('INVENTORY_COST_NOT_INITIALIZED', $exception->errors()['inventory_cost'][0]);
        }

        $this->assertSame($before, [$level->fresh()->on_hand, $level->fresh()->reserved]);
        $this->assertNull($fulfillment->items()->sole()->unit_cost_snapshot);
    }

    public function test_order_profitability_uses_fulfilled_revenue_and_snapshots(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 4);
        $this->makeCod($tenant, $store, $order);
        $this->complete($tenant, $this->fulfillment($tenant, $order, 2));

        $profitability = app(OrderProfitability::class)->build($order);
        $line = $order->items()->sole();
        $expectedRevenue = (string) BigDecimal::of($line->line_total)
            ->minus($line->tax_amount ?? '0')->dividedBy(2, 4, RoundingMode::HalfUp);
        $this->assertSame($expectedRevenue, $profitability['fulfilled_revenue_ex_tax']);
        $this->assertSame('20.0000', $profitability['cogs']);
        $this->assertSame('100.00', $profitability['cost_coverage_percent']);
        $this->assertSame('COMPLETE', $profitability['status']);
    }

    public function test_dashboard_withholds_margin_when_legacy_fulfillment_has_no_cost_snapshot(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 2);
        $this->makeCod($tenant, $store, $order);
        $reservation = $this->reservationFor($order);
        $costed = $this->fulfillment($tenant, $order, 1, $reservation);
        $legacy = $this->fulfillment($tenant, $order, 1, $reservation);
        $this->complete($tenant, $costed);
        $legacy->update([
            'status' => FulfillmentStatus::Fulfilled,
            'started_at' => now(),
            'fulfilled_at' => now(),
        ]);

        $metrics = app(ProfitabilityDashboardMetrics::class)->build(
            $tenant->getKey(), $store->getKey(), $store->currency,
            DashboardDateRange::make(DashboardRange::Today, $store->timezone),
        );

        $this->assertSame([2, 1, '50.00', 'INCOMPLETE'], [
            $metrics['fulfilled_quantity'], $metrics['covered_quantity'],
            $metrics['cost_coverage_percent'], $metrics['status'],
        ]);
        $this->assertSame('10.0000', $metrics['cogs']);
        $this->assertNull($metrics['gross_profit']);
        $this->assertNull($metrics['gross_margin_percent']);
    }
}
