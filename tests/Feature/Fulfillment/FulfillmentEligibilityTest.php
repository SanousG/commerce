<?php

namespace Tests\Feature\Fulfillment;

use App\Models\User;
use App\Modules\Fulfillment\Application\CreateFulfillment;
use App\Modules\Payments\Domain\Enums\CodDepositType;
use Illuminate\Validation\ValidationException;

class FulfillmentEligibilityTest extends FulfillmentFeatureTestCase
{
    public function test_prepaid_requires_full_payment_before_fulfillment(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $user);
        $reservation = $this->reservationFor($order);
        $location = $reservation->location;
        $item = $order->items()->sole();

        foreach (['unpaid', 'partial'] as $state) {
            if ($state === 'partial') {
                $this->enablePayments($tenant, $store);
                $this->pay($tenant, $store, $order, '10.0000', $user);
            }
            try {
                app(CreateFulfillment::class)->handle($tenant, $order, $location, [[
                    'order_item_id' => $item->getKey(),
                    'inventory_reservation_id' => $reservation->getKey(),
                    'quantity' => 1,
                ]], $user);
                $this->fail("Prepaid {$state} order was accepted for fulfillment.");
            } catch (ValidationException $exception) {
                $this->assertSame(['PREPAID_PAYMENT_REQUIRED'], $exception->errors()['payment']);
            }
        }

        $this->pay($tenant, $store, $order, '190.0000', $user);
        $this->assertNotNull($this->fulfillment($tenant, $order, 2, actor: $user));
    }

    public function test_cod_requires_due_now_but_none_allows_zero_capture(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        [$depositOrder] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $user);
        $this->makeCod($tenant, $store, $depositOrder, CodDepositType::FixedAmount, '30.0000');
        $this->pay($tenant, $store, $depositOrder, '29.0000', $user);

        try {
            $this->fulfillment($tenant, $depositOrder, 1, actor: $user);
            $this->fail('COD order below amount_due_now was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['COD_DEPOSIT_REQUIRED'], $exception->errors()['payment']);
        }

        $this->pay($tenant, $store, $depositOrder, '1.0000', $user);
        $this->assertNotNull($this->fulfillment($tenant, $depositOrder, 1, actor: $user));

        [$zeroDueOrder] = $this->readyOrder($tenant, $store, $user);
        $this->makeCod($tenant, $store, $zeroDueOrder);
        $this->assertNotNull($this->fulfillment($tenant, $zeroDueOrder, 1, actor: $user));
    }
}
