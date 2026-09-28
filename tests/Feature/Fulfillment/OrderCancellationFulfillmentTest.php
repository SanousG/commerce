<?php

namespace Tests\Feature\Fulfillment;

use App\Models\User;
use App\Modules\Fulfillment\Application\StartFulfillment;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Orders\Application\CancelOrder;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Illuminate\Validation\ValidationException;

class OrderCancellationFulfillmentTest extends FulfillmentFeatureTestCase
{
    public function test_order_cancellation_cancels_open_fulfillment_and_releases_inventory(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $actor);
        $this->makeCod($tenant, $store, $order);
        $reservation = $this->reservationFor($order);
        $fulfillment = app(StartFulfillment::class)->handle(
            $tenant,
            $this->fulfillment($tenant, $order, 1, $reservation, actor: $actor),
            $actor,
        );

        app(CancelOrder::class)->handle($tenant, $order, 'Customer request.', $actor);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(FulfillmentStatus::Cancelled, $fulfillment->fresh()->status);
        $this->assertSame('Customer request.', $fulfillment->fresh()->cancellation_reason);
        $this->assertSame(InventoryReservationStatus::Released, $reservation->fresh()->status);
        $this->assertSame(0, $level->fresh()->reserved);
        $this->assertSame($actor->getKey(), $fulfillment->fresh()->history()->latest('created_at')->firstOrFail()->actor_user_id);
    }

    public function test_capture_precedes_fulfillment_guard_and_fulfilled_requires_return(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$captured] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->makeCod($tenant, $store, $captured);
        $this->pay($tenant, $store, $captured, '1.0000');
        $open = $this->fulfillment($tenant, $captured, 1);

        try {
            app(CancelOrder::class)->handle($tenant, $captured, 'Cancel paid order.');
            $this->fail('Captured order was cancelled.');
        } catch (ValidationException $exception) {
            $this->assertStringStartsWith('PAYMENT_REFUND_REQUIRED', $exception->errors()['order'][0]);
            $this->assertSame(FulfillmentStatus::Pending, $open->fresh()->status);
        }

        [$fulfilledOrder] = $this->readyOrder($tenant, $store, quantity: 1);
        $this->makeCod($tenant, $store, $fulfilledOrder);
        $this->complete($tenant, $this->fulfillment($tenant, $fulfilledOrder, 1));
        try {
            app(CancelOrder::class)->handle($tenant, $fulfilledOrder, 'Cancel shipped stock.');
            $this->fail('Fulfilled order was cancelled.');
        } catch (ValidationException $exception) {
            $this->assertStringStartsWith('FULFILLMENT_RETURN_REQUIRED', $exception->errors()['order'][0]);
        }
    }
}
