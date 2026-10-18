<?php

namespace Tests\Feature\Fulfillment;

use App\Models\User;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use Inertia\Testing\AssertableInertia as Assert;

class FulfillmentListFilterTest extends FulfillmentFeatureTestCase
{
    public function test_fulfillments_index_filters_by_status_order_and_search(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        [$order] = $this->readyOrder($tenant, $store, $user, 2);
        $this->makeCod($tenant, $store, $order);
        $fulfillment = $this->fulfillment($tenant, $order, 2, actor: $user);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.fulfillments.index'))->assertInertia(
            fn (Assert $page) => $page->has('fulfillments.data', 1)
                ->where('fulfillments.data.0.id', $fulfillment->getKey()),
        );
        $this->get(route('admin.fulfillments.index', ['order_id' => $order->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('fulfillments.data', 1),
        );
        $this->get(route('admin.fulfillments.index', ['search' => $order->order_number]))->assertInertia(
            fn (Assert $page) => $page->has('fulfillments.data', 1),
        );
        $this->get(route('admin.fulfillments.index', ['status' => FulfillmentStatus::Pending->value]))->assertInertia(
            fn (Assert $page) => $page->has('fulfillments.data', 1),
        );
        $this->get(route('admin.fulfillments.index', ['status' => FulfillmentStatus::Fulfilled->value]))->assertInertia(
            fn (Assert $page) => $page->has('fulfillments.data', 0),
        );
        $this->get(route('admin.fulfillments.index', ['status' => 'NOT_A_STATUS']))->assertSessionHasErrors('status');
        $this->get(route('admin.fulfillments.index', ['order_id' => 'not-a-uuid']))->assertSessionHasErrors('order_id');
    }

    public function test_fulfillments_index_is_store_scoped(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        [$orderB] = $this->readyOrder($tenant, $storeB, $user, 2);
        $this->makeCod($tenant, $storeB, $orderB);
        $fulfillmentB = $this->fulfillment($tenant, $orderB, 2, actor: $user);
        $this->useContext($user, $tenant, $storeA);

        $this->get(route('admin.fulfillments.index'))->assertInertia(
            fn (Assert $page) => $page->has('fulfillments.data', 0),
        );
        $this->get(route('admin.fulfillments.index', ['order_id' => $orderB->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('fulfillments.data', 0),
        );
        $this->get(route('admin.fulfillments.show', $fulfillmentB))->assertNotFound();
    }
}
