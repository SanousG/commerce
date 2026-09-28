<?php

namespace Tests\Feature\Fulfillment;

use App\Models\User;
use App\Modules\Fulfillment\Application\CancelFulfillment;
use App\Modules\Fulfillment\Application\CompleteFulfillment;
use App\Modules\Fulfillment\Application\StartFulfillment;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Illuminate\Validation\ValidationException;

class FulfillmentLifecycleTest extends FulfillmentFeatureTestCase
{
    public function test_create_is_inventory_neutral_and_complete_commits_with_auditable_projection(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $actor, 3);
        $this->makeCod($tenant, $store, $order);
        $reservation = $this->reservationFor($order);
        $before = [$level->fresh()->on_hand, $level->fresh()->reserved, $level->fresh()->available];
        $fulfillment = $this->fulfillment($tenant, $order, 2, $reservation, actor: $actor);

        $this->assertSame($before, [$level->fresh()->on_hand, $level->fresh()->reserved, $level->fresh()->available]);
        $this->assertSame(0, $reservation->fresh()->committed_quantity);
        $this->assertSame(FulfillmentStatus::Pending, $fulfillment->status);
        app(StartFulfillment::class)->handle($tenant, $fulfillment, $actor);
        $completed = app(CompleteFulfillment::class)->handle($tenant, $fulfillment->fresh(), $actor);

        $this->assertSame([$before[0] - 2, $before[1] - 2, $before[2]], [
            $level->fresh()->on_hand, $level->fresh()->reserved, $level->fresh()->available,
        ]);
        $this->assertSame(2, $reservation->fresh()->committed_quantity);
        $this->assertSame(InventoryReservationStatus::Active, $reservation->fresh()->status);
        $movement = InventoryMovement::query()->where('type', InventoryMovementType::ReservationCommit)->sole();
        $this->assertSame('FULFILLMENT_ITEM', $movement->reference_type);
        $this->assertSame($completed->items->sole()->getKey(), $movement->reference_id);
        $this->assertSame($completed->getKey(), $movement->correlation_id);
        $this->assertSame(-2, $movement->quantity_delta);
        $this->assertSame($actor->getKey(), $movement->actor_user_id);
        $this->assertSame(
            [FulfillmentStatus::Pending, FulfillmentStatus::Processing, FulfillmentStatus::Fulfilled],
            $completed->history->pluck('to_status')->all(),
        );
        $this->assertSame([$actor->getKey(), $actor->getKey(), $actor->getKey()], $completed->history->pluck('actor_user_id')->all());
        $this->assertSame(OrderFulfillmentStatus::PartiallyFulfilled, $order->fresh()->fulfillment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->fresh()->payment_status);

        $again = app(CompleteFulfillment::class)->handle($tenant, $completed, $actor);
        $this->assertSame($completed->getKey(), $again->getKey());
        $this->assertSame(1, InventoryMovement::query()->where('type', InventoryMovementType::ReservationCommit)->count());
        $this->assertCount(3, $again->history);
    }

    public function test_same_reservation_can_be_fulfilled_five_then_three_exactly_once(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 8);
        $this->makeCod($tenant, $store, $order);
        $reservation = $this->reservationFor($order);
        $onHand = $level->fresh()->on_hand;

        $first = $this->complete($tenant, $this->fulfillment($tenant, $order, 5, $reservation));
        $this->assertSame(OrderFulfillmentStatus::PartiallyFulfilled, $order->fresh()->fulfillment_status);
        $this->assertSame(5, $reservation->fresh()->committed_quantity);
        $this->assertSame(InventoryReservationStatus::Active, $reservation->fresh()->status);

        $second = $this->complete($tenant, $this->fulfillment($tenant, $order, 3, $reservation));
        $this->assertSame(OrderFulfillmentStatus::Fulfilled, $order->fresh()->fulfillment_status);
        $this->assertSame(InventoryReservationStatus::Committed, $reservation->fresh()->status);
        $movements = InventoryMovement::query()->where('type', InventoryMovementType::ReservationCommit)
            ->get()->keyBy('correlation_id');
        $this->assertSame([-5, $onHand, $onHand - 5], [
            $movements[$first->getKey()]->quantity_delta,
            $movements[$first->getKey()]->quantity_before,
            $movements[$first->getKey()]->quantity_after,
        ]);
        $this->assertSame([-3, $onHand - 5, $onHand - 8], [
            $movements[$second->getKey()]->quantity_delta,
            $movements[$second->getKey()]->quantity_before,
            $movements[$second->getKey()]->quantity_after,
        ]);
        $this->assertSame($onHand - 8, $level->fresh()->on_hand);
    }

    public function test_completion_revalidates_reservation_quantity_before_inventory_mutation(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->makeCod($tenant, $store, $order);
        $reservation = $this->reservationFor($order);
        $fulfillment = app(StartFulfillment::class)->handle(
            $tenant,
            $this->fulfillment($tenant, $order, 2, $reservation),
        );
        $before = [$level->fresh()->on_hand, $level->fresh()->reserved];
        $reservation->update(['quantity' => 1]);

        try {
            app(CompleteFulfillment::class)->handle($tenant, $fulfillment);
            $this->fail('Completion accepted a stale quantity above the reservation remainder.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reservation', $exception->errors());
        }
        $this->assertSame($before, [$level->fresh()->on_hand, $level->fresh()->reserved]);
        $this->assertSame(0, InventoryMovement::query()->where('type', InventoryMovementType::ReservationCommit)->count());
    }

    public function test_location_mismatch_is_rejected_and_two_locations_commit_independently(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $actor, 8);
        $this->makeCod($tenant, $store, $order);
        [$first, $second, $secondLocation] = $this->splitReservationAcrossLocations($tenant, $order, 4, 4, $actor);

        try {
            $this->fulfillment($tenant, $order, 1, $first, $secondLocation, $actor);
            $this->fail('A reservation was allocated through a different location.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reservation', $exception->errors());
        }

        $firstFulfillment = $this->complete($tenant, $this->fulfillment($tenant, $order, 4, $first, actor: $actor), $actor);
        $this->assertSame(OrderFulfillmentStatus::PartiallyFulfilled, $order->fresh()->fulfillment_status);
        $secondFulfillment = $this->complete($tenant, $this->fulfillment($tenant, $order, 4, $second, actor: $actor), $actor);
        $this->assertNotSame($firstFulfillment->inventory_location_id, $secondFulfillment->inventory_location_id);
        $this->assertSame(OrderFulfillmentStatus::Fulfilled, $order->fresh()->fulfillment_status);
    }

    public function test_cancelling_pending_or_processing_is_inventory_neutral_and_fulfilled_is_rejected(): void
    {
        foreach ([false, true] as $start) {
            $tenant = $this->createTenantFor(User::factory()->create());
            [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
            $this->makeCod($tenant, $store, $order);
            $reservation = $this->reservationFor($order);
            $before = [$level->fresh()->on_hand, $level->fresh()->reserved];
            $fulfillment = $this->fulfillment($tenant, $order, 1, $reservation);
            if ($start) {
                $fulfillment = app(StartFulfillment::class)->handle($tenant, $fulfillment);
            }
            app(CancelFulfillment::class)->handle($tenant, $fulfillment, 'No longer needed.');
            $this->assertSame($before, [$level->fresh()->on_hand, $level->fresh()->reserved]);
            $this->assertSame(InventoryReservationStatus::Active, $reservation->fresh()->status);
        }

        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 1);
        $this->makeCod($tenant, $store, $order);
        $fulfilled = $this->complete($tenant, $this->fulfillment($tenant, $order, 1));
        $this->expectException(ValidationException::class);
        app(CancelFulfillment::class)->handle($tenant, $fulfilled, 'Cannot undo committed stock.');
    }
}
