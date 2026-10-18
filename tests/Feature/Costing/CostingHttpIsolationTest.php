<?php

namespace Tests\Feature\Costing;

use App\Models\User;
use App\Modules\Costing\Application\InitializeInventoryCost;
use App\Modules\Tenancy\Application\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Inventory\InventoryFeatureTestCase;

class CostingHttpIsolationTest extends InventoryFeatureTestCase
{
    public function test_valuation_report_is_tenant_scoped_and_exposes_cost_only_to_authorized_user(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant, 'OWN-COST');
        $this->stock($tenant, $location, $variant, 3, $owner);
        app(InitializeInventoryCost::class)->handle($tenant, $location, $variant, '7.5', 'PEN', $owner);

        $other = $this->createTenantFor(User::factory()->create());
        $otherLocation = $other->defaultInventoryLocation()->sole();
        $otherVariant = $this->createVariant($other, 'FOREIGN-COST');
        $this->stock($other, $otherLocation, $otherVariant, 9);
        app(InitializeInventoryCost::class)->handle($other, $otherLocation, $otherVariant, '999', 'USD');

        $this->actingAs($owner)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->get(route('admin.inventory.cost.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('valuations.data', 1)
                ->where('valuations.data.0.sku', 'OWN-COST')
                ->where('valuations.data.0.average_unit_cost', '7.500000')
                ->where('valuations.data.0.inventory_value', '22.5000')
                ->has('totals', 1)
                ->where('totals.0.currency', 'PEN'));
    }
}
