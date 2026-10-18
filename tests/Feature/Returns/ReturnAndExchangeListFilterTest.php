<?php

namespace Tests\Feature\Returns;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Returns\Application\CreateExchange;
use App\Modules\Returns\Domain\Enums\ExchangeStatus;
use App\Modules\Returns\Domain\Enums\ReturnItemCondition;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\Exchange;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

class ReturnAndExchangeListFilterTest extends ReturnsFeatureTestCase
{
    private function pendingExchange(
        Tenant $tenant,
        Store $store,
        ReturnRequest $return,
        ProductVariant $sourceVariant,
    ): Exchange {
        $this->receiveReturn($tenant, $store, $return, 1);
        $item = $this->inspectReturnItem(
            $tenant,
            $store,
            $return->fresh(),
            $return->items()->sole(),
            1,
            ReturnItemCondition::OpenBox,
            ReturnItemDisposition::NoAction,
        );
        $replacement = $this->addVariant($tenant, $sourceVariant->product);

        return app(CreateExchange::class)->handle($tenant, $store, $return->fresh(), [[
            'return_item_id' => $item->getKey(),
            'replacement_product_variant_id' => $replacement->getKey(),
            'quantity' => 1,
        ]]);
    }

    public function test_returns_index_filters_by_status_order_and_search(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        [$order] = $this->fulfilledOrder($tenant, $store, 3, actor: $user);
        $return = $this->requestReturn($tenant, $store, $order, 1, $user);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.returns.index'))->assertInertia(
            fn (Assert $page) => $page->has('returns.data', 1)->where('returns.data.0.id', $return->getKey()),
        );
        $this->get(route('admin.returns.index', ['order_id' => $order->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('returns.data', 1),
        );
        $this->get(route('admin.returns.index', ['search' => $order->order_number]))->assertInertia(
            fn (Assert $page) => $page->has('returns.data', 1),
        );
        $this->get(route('admin.returns.index', ['status' => ReturnStatus::Requested->value]))->assertInertia(
            fn (Assert $page) => $page->has('returns.data', 1),
        );
        $this->get(route('admin.returns.index', ['status' => ReturnStatus::Completed->value]))->assertInertia(
            fn (Assert $page) => $page->has('returns.data', 0),
        );
        $this->get(route('admin.returns.index', ['status' => 'NOT_A_STATUS']))->assertSessionHasErrors('status');
    }

    public function test_exchanges_index_filters_by_status_order_and_search(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        [$order, , , $variant] = $this->fulfilledOrder($tenant, $store, 3, actor: $user);
        $return = $this->requestReturn($tenant, $store, $order, 1, $user);
        $exchange = $this->pendingExchange($tenant, $store, $return, $variant);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.exchanges.index'))->assertInertia(
            fn (Assert $page) => $page->has('exchanges.data', 1)->where('exchanges.data.0.id', $exchange->getKey()),
        );
        $this->get(route('admin.exchanges.index', ['order_id' => $order->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('exchanges.data', 1),
        );
        $this->get(route('admin.exchanges.index', ['search' => $order->order_number]))->assertInertia(
            fn (Assert $page) => $page->has('exchanges.data', 1),
        );
        $this->get(route('admin.exchanges.index', ['status' => ExchangeStatus::Pending->value]))->assertInertia(
            fn (Assert $page) => $page->has('exchanges.data', 1),
        );
        $this->get(route('admin.exchanges.index', ['status' => ExchangeStatus::Completed->value]))->assertInertia(
            fn (Assert $page) => $page->has('exchanges.data', 0),
        );
        $this->get(route('admin.exchanges.index', ['status' => 'NOT_A_STATUS']))->assertSessionHasErrors('status');
    }

    public function test_return_and_exchange_lists_are_store_scoped(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        [$orderB] = $this->fulfilledOrder($tenant, $storeB, 3, actor: $user);
        $returnB = $this->requestReturn($tenant, $storeB, $orderB, 1, $user);
        $this->useContext($user, $tenant, $storeA);

        $this->get(route('admin.returns.index'))->assertInertia(
            fn (Assert $page) => $page->has('returns.data', 0),
        );
        $this->get(route('admin.returns.index', ['order_id' => $orderB->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('returns.data', 0),
        );
        $this->get(route('admin.returns.show', $returnB))->assertNotFound();
    }
}
