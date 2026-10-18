<?php

namespace Tests\Feature\Costing;

use App\Models\User;
use App\Modules\Costing\Application\InitializeInventoryCost;
use App\Modules\Costing\Domain\Models\InventoryCostMovement;
use App\Modules\Costing\Domain\Models\InventoryValuation;
use Illuminate\Database\QueryException;
use Tests\Feature\Inventory\InventoryFeatureTestCase;

class CostingDatabaseConstraintTest extends InventoryFeatureTestCase
{
    public function test_cost_movement_is_append_only_in_postgresql(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $location = $this->createLocation($tenant);
        $this->stock($tenant, $location, $variant, 2, $actor);
        $movement = app(InitializeInventoryCost::class)->handle($tenant, $location, $variant, '4', 'PEN', $actor);

        $this->expectException(QueryException::class);
        InventoryCostMovement::query()->whereKey($movement->getKey())->update(['unit_cost' => '5.000000']);
    }

    public function test_valuation_scope_is_unique(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $location = $this->createLocation($tenant);
        $this->stock($tenant, $location, $variant, 2, $actor);
        app(InitializeInventoryCost::class)->handle($tenant, $location, $variant, '4', 'PEN', $actor);

        $this->expectException(QueryException::class);
        InventoryValuation::create([
            'tenant_id' => $tenant->getKey(),
            'inventory_location_id' => $location->getKey(),
            'product_variant_id' => $variant->getKey(),
            'average_unit_cost' => '4.000000',
            'currency' => 'PEN',
        ]);
    }
}
