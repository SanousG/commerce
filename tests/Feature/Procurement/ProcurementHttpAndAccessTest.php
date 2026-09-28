<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Inertia\Testing\AssertableInertia;

class ProcurementHttpAndAccessTest extends ProcurementFeatureTestCase
{
    public function test_procurement_permission_matrix_is_exact(): void
    {
        $all = [
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.archive',
            'procurement.view', 'procurement.manage', 'procurement.approve', 'procurement.receive',
        ];
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $this->assertEqualsCanonicalizing($all, Role::query()->system($role)->sole()->permissions()
                ->whereIn('module', ['suppliers', 'procurement'])->pluck('key')->all());
        }
        $this->assertEqualsCanonicalizing(
            ['suppliers.view', 'procurement.view'],
            Role::query()->system(SystemRole::Member)->sole()->permissions()
                ->whereIn('module', ['suppliers', 'procurement'])->pluck('key')->all(),
        );
    }

    public function test_procurement_routes_require_tenant_but_not_store_context(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.suppliers.index'))
            ->assertRedirect(route('admin.tenants.create'));

        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->supplier($tenant);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->get(route('admin.suppliers.index'))->assertOk()->assertSessionMissing(StoreContext::SESSION_KEY);
        $this->get(route('admin.purchase-orders.index'))->assertOk()->assertSessionMissing(StoreContext::SESSION_KEY);
    }

    public function test_switching_store_does_not_change_procurement_lists(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $supplier = $this->supplier($tenant);
        $storeA = $this->createStore($tenant, 'Store A');
        $storeB = $this->createStore($tenant, 'Store B');

        foreach ([$storeA, $storeB] as $store) {
            $this->useTenant($user, $tenant, $store);
            $this->get(route('admin.suppliers.index'))->assertInertia(fn (AssertableInertia $page) => $page
                ->has('suppliers.data', 1)->where('suppliers.data.0.id', $supplier->getKey()));
        }
    }

    public function test_switching_tenant_changes_procurement_lists_and_clears_store(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $supplierA = $this->supplier($tenantA, 'TENANT-A');
        $supplierB = $this->supplier($tenantB, 'TENANT-B');
        $storeA = $this->createStore($tenantA);
        $this->useTenant($user, $tenantA, $storeA);

        $this->get(route('admin.suppliers.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('suppliers.data.0.id', $supplierA->getKey()));
        $this->post(route('admin.tenants.select', $tenantB))->assertSessionMissing(StoreContext::SESSION_KEY);
        $this->get(route('admin.suppliers.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('suppliers.data', 1)->where('suppliers.data.0.id', $supplierB->getKey()));
    }

    public function test_member_can_view_purchase_orders_but_all_procurement_mutations_are_forbidden(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $this->createVariant($tenant));
        $order = $this->draftOrder($tenant, $supplier, $product);
        $this->useTenant($member, $tenant);

        $this->get(route('admin.purchase-orders.index'))->assertOk();
        $this->get(route('admin.purchase-orders.show', $order))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('capabilities.update', false)->where('capabilities.approve', false)->where('capabilities.receive', false));
        $this->post(route('admin.purchase-orders.store'), [])->assertForbidden();
        $this->patch(route('admin.purchase-orders.update', $order), [])->assertForbidden();
        $this->post(route('admin.purchase-orders.items.store', $order), [])->assertForbidden();
        $this->post(route('admin.purchase-orders.approve', $order))->assertForbidden();
        $this->post(route('admin.purchase-orders.mark-ordered', $order))->assertForbidden();
        $this->post(route('admin.purchase-orders.cancel', $order))->assertForbidden();
        $this->post(route('admin.purchase-orders.receipts.store', $order), [])->assertForbidden();
    }

    public function test_cross_tenant_purchase_order_routes_return_not_found(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $supplier = $this->supplier($tenantB);
        $order = $this->draftOrder($tenantB, $supplier, $this->supplierProduct($tenantB, $supplier, $this->createVariant($tenantB)));
        $this->useTenant($user, $tenantA);

        $this->get(route('admin.purchase-orders.show', $order))->assertNotFound();
        $this->patch(route('admin.purchase-orders.update', $order), [])->assertNotFound();
        $this->post(route('admin.purchase-orders.approve', $order))->assertNotFound();
        $this->post(route('admin.purchase-orders.cancel', $order))->assertNotFound();
    }
}
