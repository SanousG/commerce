<?php

namespace Tests\Feature\Returns;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use Inertia\Testing\AssertableInertia;

class ReturnHttpAndIsolationTest extends ReturnsFeatureTestCase
{
    public function test_member_is_view_only_for_returns_and_exchanges(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        [$order] = $this->fulfilledOrder($tenant, $store = $this->createStore($tenant));
        $return = $this->requestReturn($tenant, $store, $order, 1);
        $this->useContext($member, $tenant, $store);

        $this->get(route('admin.returns.index'))->assertOk();
        $this->get(route('admin.returns.show', $return))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('capabilities.manage', false)
            ->where('capabilities.inspect', false)
            ->where('capabilities.restock', false)
            ->where('capabilities.manage_exchanges', false));
        $this->get(route('admin.exchanges.index'))->assertOk();
        $this->post(route('admin.orders.returns.store', $order), [
            'items' => [['order_item_id' => $order->items()->sole()->getKey(), 'quantity' => 1]],
        ])->assertForbidden();
        $this->post(route('admin.returns.approve', $return))->assertForbidden();
        $this->post(route('admin.returns.reject', $return), ['reason' => 'Denied'])->assertForbidden();
        $this->post(route('admin.returns.cancel', $return), ['reason' => 'Denied'])->assertForbidden();
        $this->post(route('admin.returns.exchanges.store', $return), ['items' => []])->assertForbidden();
    }

    public function test_return_routes_hide_other_store_and_tenant_records(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        [$orderB] = $this->fulfilledOrder($tenant, $storeB = $this->createStore($tenant));
        $returnB = $this->requestReturn($tenant, $storeB, $orderB, 1);

        $otherTenant = $this->createTenantFor(User::factory()->create());
        [$otherOrder] = $this->fulfilledOrder($otherTenant, $otherStore = $this->createStore($otherTenant));
        $otherReturn = $this->requestReturn($otherTenant, $otherStore, $otherOrder, 1);
        $this->useContext($user, $tenant, $storeA);

        foreach ([[$orderB, $returnB], [$otherOrder, $otherReturn]] as [$order, $return]) {
            $item = $return->items()->sole();
            $this->get(route('admin.returns.show', $return))->assertNotFound();
            $this->post(route('admin.orders.returns.store', $order), [
                'items' => [['order_item_id' => $order->items()->sole()->getKey(), 'quantity' => 1]],
            ])->assertNotFound();
            $this->post(route('admin.returns.approve', $return))->assertNotFound();
            $this->post(route('admin.returns.reject', $return), ['reason' => 'Isolation'])->assertNotFound();
            $this->post(route('admin.returns.receive', $return), [
                'items' => [['return_item_id' => $item->getKey(), 'quantity' => 1]],
            ])->assertNotFound();
            $this->post(route('admin.returns.complete', $return))->assertNotFound();
            $this->post(route('admin.returns.cancel', $return), ['reason' => 'Isolation'])->assertNotFound();
            $this->post(route('admin.return-items.inspect', $item), [
                'condition' => 'SELLABLE', 'disposition' => 'RESTOCK', 'accepted_quantity' => 1,
            ])->assertNotFound();
            $this->post(route('admin.return-items.restock', $item), [
                'inventory_location_id' => $this->reservationFor($order)->inventory_location_id, 'quantity' => 1,
            ])->assertNotFound();
            $this->post(route('admin.returns.exchanges.store', $return), ['items' => [[
                'return_item_id' => $item->getKey(),
                'replacement_product_variant_id' => $order->items()->sole()->product_variant_id,
                'quantity' => 1,
            ]]])->assertNotFound();
        }
    }
}
