<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Inertia\Testing\AssertableInertia;

class OrderHttpAndIsolationTest extends OrdersFeatureTestCase
{
    public function test_owner_and_admin_manage_while_member_only_views(): void
    {
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $user = User::factory()->create();
            $tenant = $this->createTenantFor($user, $role);
            [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $user);
            $this->useContext($user, $tenant, $store);
            $this->get(route('admin.orders.index'))->assertOk();
            $this->get(route('admin.orders.show', $order))->assertOk();
            $this->post(route('admin.orders.cancel', $order), ['reason' => 'Administrative cancellation'])->assertRedirect();
        }

        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        [$pending] = $this->pendingCheckout($store);
        $ready = $this->makeReady($tenant, $pending);
        $this->useContext($member, $tenant, $store);
        $this->get(route('admin.orders.index'))->assertOk();
        $this->get(route('admin.orders.show', $order))->assertInertia(
            fn (AssertableInertia $page) => $page->where('canCancel', false),
        );
        $this->post(route('admin.checkouts.order.store', $ready))->assertForbidden();
        $this->post(route('admin.orders.cancel', $order), ['reason' => 'Not allowed'])->assertForbidden();
    }

    public function test_create_show_and_cancel_are_strictly_store_and_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        [$orderA] = $this->readyOrder($tenant, $storeA, $user);
        [$orderB, $checkoutB] = $this->readyOrder($tenant, $this->createStore($tenant));
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        [$foreign] = $this->readyOrder($foreignTenant, $this->createStore($foreignTenant));
        $this->useContext($user, $tenant, $storeA);

        $this->get(route('admin.orders.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/orders/Index')->has('orders.data', 1)->where('orders.data.0.id', $orderA->getKey()));
        $this->get(route('admin.orders.show', $orderB))->assertNotFound();
        $this->get(route('admin.orders.show', $foreign))->assertNotFound();
        $this->post(route('admin.orders.cancel', $orderB), ['reason' => 'Wrong store'])->assertNotFound();
        $this->post(route('admin.checkouts.order.store', $checkoutB))->assertNotFound();
    }

    public function test_index_searches_filters_and_paginates_without_other_store_data(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        for ($index = 0; $index < 21; $index++) {
            $this->readyOrder($tenant, $store, $user, 1);
        }
        [$otherStoreOrder] = $this->readyOrder($tenant, $this->createStore($tenant), $user);
        $target = $store->orders()->orderBy('order_number')->firstOrFail();
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.orders.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 20)->where('orders.per_page', 20));
        $this->get(route('admin.orders.index', ['search' => $target->order_number]))->assertInertia(
            fn (AssertableInertia $page) => $page->has('orders.data', 1)
                ->where('orders.data.0.id', $target->getKey()),
        );
        $this->get(route('admin.orders.index', ['status' => OrderStatus::Cancelled->value]))->assertInertia(
            fn (AssertableInertia $page) => $page->has('orders.data', 0),
        );
        $this->assertNotSame($target->getKey(), $otherStoreOrder->getKey());
    }

    public function test_checkout_show_exposes_order_action_or_existing_order_link(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        [$pending] = $this->pendingCheckout($store);
        $ready = $this->makeReady($tenant, $pending);
        $this->useContext($user, $tenant, $store);
        $this->get(route('admin.checkouts.show', $ready))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canCreateOrder', true)->where('checkout.order', null));
        $this->post(route('admin.checkouts.order.store', $ready))->assertRedirect();
        $order = $ready->order()->firstOrFail();
        $this->get(route('admin.checkouts.show', $ready))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('checkout.order.id', $order->getKey())->where('checkout.order.order_number', $order->order_number));
    }
}
