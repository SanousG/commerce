<?php

namespace Tests\Feature\Costing;

use App\Models\User;
use App\Modules\Costing\Domain\Enums\InventoryCostMovementType;
use App\Modules\Costing\Domain\Models\InventoryCostMovement;
use App\Modules\Costing\Domain\Models\InventoryValuation;
use App\Modules\Returns\Application\RestockReturnItem;
use Tests\Feature\Returns\ReturnsFeatureTestCase;

class ReturnRestockCostingTest extends ReturnsFeatureTestCase
{
    public function test_restock_uses_historical_fulfillment_cost_not_current_average(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $level] = $this->fulfilledOrder(
            $tenant, $store = $this->createStore($tenant), orderedQuantity: 3, actor: $actor,
        );
        $valuation = InventoryValuation::query()->sole();
        $valuation->update(['average_unit_cost' => '120.000000']);
        $return = $this->requestReturn($tenant, $store, $order, 2, $actor);
        $return = $this->receiveReturn($tenant, $store, $return, 2, $actor);
        $item = $this->inspectReturnItem($tenant, $store, $return, $return->items()->sole(), 2, actor: $actor);

        app(RestockReturnItem::class)->handle($tenant, $store, $return, $item, $level->location, 2, $actor);

        $this->assertSame(19, $level->fresh()->on_hand);
        $this->assertSame('108.421053', $valuation->fresh()->average_unit_cost);
        $movement = InventoryCostMovement::query()->where('movement_type', InventoryCostMovementType::ReturnRestock)->sole();
        $this->assertSame(['10.000000', '20.0000'], [$movement->unit_cost, $movement->value_delta]);
    }
}
