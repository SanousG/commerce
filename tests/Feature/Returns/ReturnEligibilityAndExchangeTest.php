<?php

namespace Tests\Feature\Returns;

use App\Models\User;
use App\Modules\Returns\Application\CreateExchange;
use App\Modules\Returns\Application\ReturnEligibility;
use App\Modules\Returns\Domain\Enums\ReturnItemCondition;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ExchangeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnEligibilityAndExchangeTest extends ReturnsFeatureTestCase
{
    public function test_eligibility_uses_only_fulfilled_quantity_and_supports_partial_fulfillment(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 5);
        $this->makeCod($tenant, $store, $order);
        $item = $order->items()->sole();
        $pending = $this->fulfillment($tenant, $order, 2);
        $eligibility = app(ReturnEligibility::class);

        $this->assertSame(0, $eligibility->fulfilledQuantity($tenant, $item));
        $this->assertSame(0, $eligibility->returnableQuantity($tenant, $item));

        $this->complete($tenant, $pending);
        $this->assertSame(2, $eligibility->fulfilledQuantity($tenant, $item));
        $this->assertSame(2, $eligibility->returnableQuantity($tenant, $item));
        $return = $this->requestReturn($tenant, $store, $order, 2);
        $this->assertSame(2, $return->items->sole()->requested_quantity);

        try {
            $this->requestReturn($tenant, $store, $order, 3);
            $this->fail('A return exceeded the fulfilled quantity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
    }

    public function test_request_is_inventory_payment_transaction_and_order_status_neutral(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $level] = $this->fulfilledOrder($tenant, $store = $this->createStore($tenant), actor: $actor);
        $beforeLevel = [$level->on_hand, $level->reserved, $level->available];
        $beforeOrder = [$order->status, $order->payment_status, $order->fulfillment_status];
        $paymentCount = DB::table('payments')->count();
        $transactionCount = DB::table('payment_transactions')->count();

        $return = $this->requestReturn($tenant, $store, $order, 1, $actor);

        $this->assertSame(ReturnStatus::Requested, $return->status);
        $this->assertSame($beforeLevel, [$level->fresh()->on_hand, $level->fresh()->reserved, $level->fresh()->available]);
        $this->assertSame($beforeOrder, [$order->fresh()->status, $order->fresh()->payment_status, $order->fresh()->fulfillment_status]);
        $this->assertSame($paymentCount, DB::table('payments')->count());
        $this->assertSame($transactionCount, DB::table('payment_transactions')->count());
    }

    public function test_basic_exchange_uses_accepted_quantity_and_same_tenant_variant_without_side_effects(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order, , , $variant, $level] = $this->fulfilledOrder($tenant, $store = $this->createStore($tenant), 3);
        $return = $this->requestReturn($tenant, $store, $order, 2);
        $this->receiveReturn($tenant, $store, $return, 2);
        $item = $this->inspectReturnItem(
            $tenant,
            $store,
            $return->fresh(),
            $return->items()->sole(),
            2,
            ReturnItemCondition::OpenBox,
            ReturnItemDisposition::NoAction,
        );
        $replacement = $this->addVariant($tenant, $variant->product);
        $orderItemBefore = $order->items()->sole()->getAttributes();
        $stockBefore = [$level->fresh()->on_hand, $level->fresh()->reserved];

        $exchange = app(CreateExchange::class)->handle($tenant, $store, $return->fresh(), [[
            'return_item_id' => $item->getKey(),
            'replacement_product_variant_id' => $replacement->getKey(),
            'quantity' => 2,
        ]]);

        $exchangeItem = $exchange->items->sole();
        $this->assertSame(2, $exchangeItem->quantity);
        $this->assertSame($replacement->getKey(), $exchangeItem->replacement_product_variant_id);
        $this->assertSame($orderItemBefore, $order->items()->sole()->getAttributes());
        $this->assertSame($stockBefore, [$level->fresh()->on_hand, $level->fresh()->reserved]);

        try {
            app(CreateExchange::class)->handle($tenant, $store, $return->fresh(), [[
                'return_item_id' => $item->getKey(),
                'replacement_product_variant_id' => $replacement->getKey(),
                'quantity' => 1,
            ]]);
            $this->fail('An exchange exceeded the accepted unallocated quantity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $otherTenant = $this->createTenantFor(User::factory()->create());
        [, , , $foreignVariant] = $this->readyOrder($otherTenant, $this->createStore($otherTenant), quantity: 1);
        ExchangeItem::query()->where('exchange_id', $exchange->getKey())->delete();
        $exchange->delete();
        $this->expectException(ValidationException::class);
        app(CreateExchange::class)->handle($tenant, $store, $return->fresh(), [[
            'return_item_id' => $item->getKey(),
            'replacement_product_variant_id' => $foreignVariant->getKey(),
            'quantity' => 1,
        ]]);
    }
}
