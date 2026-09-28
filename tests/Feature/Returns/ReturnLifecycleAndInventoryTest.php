<?php

namespace Tests\Feature\Returns;

use App\Models\User;
use App\Modules\Inventory\Application\CreateInventoryLocation;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Returns\Application\ApproveReturn;
use App\Modules\Returns\Application\CancelReturn;
use App\Modules\Returns\Application\CompleteReturn;
use App\Modules\Returns\Application\ReceiveReturn;
use App\Modules\Returns\Application\RejectReturn;
use App\Modules\Returns\Application\RestockReturnItem;
use App\Modules\Returns\Domain\Enums\ReturnItemCondition;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnLifecycleAndInventoryTest extends ReturnsFeatureTestCase
{
    public function test_full_lifecycle_records_history_and_preserves_historical_order_status(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $level] = $this->fulfilledOrder($tenant, $store = $this->createStore($tenant), 2, actor: $actor);
        $orderStatuses = [$order->status, $order->payment_status, $order->fulfillment_status];
        $return = $this->requestReturn($tenant, $store, $order, 1, $actor);
        $received = $this->receiveReturn($tenant, $store, $return, 1, $actor);
        $beforeInspection = [$level->fresh()->on_hand, $level->fresh()->reserved];
        $item = $this->inspectReturnItem($tenant, $store, $received, $return->items()->sole(), 1, actor: $actor);

        $this->assertSame($beforeInspection, [$level->fresh()->on_hand, $level->fresh()->reserved]);
        app(RestockReturnItem::class)->handle($tenant, $store, $return->fresh(), $item, $level->location, 1, $actor);
        $completed = app(CompleteReturn::class)->handle($tenant, $store, $return->fresh(), $actor);

        $this->assertSame(ReturnStatus::Completed, $completed->status);
        $this->assertSame(
            [ReturnStatus::Requested, ReturnStatus::Approved, ReturnStatus::Received, ReturnStatus::Inspecting, ReturnStatus::Completed],
            $completed->history->pluck('to_status')->all(),
        );
        $this->assertSame(array_fill(0, 5, $actor->getKey()), $completed->history->pluck('actor_user_id')->all());
        $this->assertSame($orderStatuses, [$order->fresh()->status, $order->fresh()->payment_status, $order->fresh()->fulfillment_status]);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->fulfilledOrder($tenant, $store = $this->createStore($tenant));
        $return = $this->requestReturn($tenant, $store, $order, 1);

        try {
            app(ReceiveReturn::class)->handle($tenant, $store, $return, [[
                'return_item_id' => $return->items->sole()->getKey(), 'quantity' => 1,
            ]]);
            $this->fail('A requested return was received before approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('return', $exception->errors());
        }

        try {
            app(CompleteReturn::class)->handle($tenant, $store, $return->fresh());
            $this->fail('A requested return was completed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('return', $exception->errors());
        }
    }

    public function test_requested_return_can_be_rejected_and_requested_or_approved_return_can_be_cancelled(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->fulfilledOrder($tenant, $store = $this->createStore($tenant), 3);

        $rejected = app(RejectReturn::class)->handle(
            $tenant, $store, $this->requestReturn($tenant, $store, $order, 1), 'Not eligible.',
        );
        $this->assertSame(ReturnStatus::Rejected, $rejected->status);
        $this->assertSame('Not eligible.', $rejected->rejection_reason);
        $this->assertSame('Not eligible.', $rejected->history->last()->reason);

        $cancelled = app(CancelReturn::class)->handle(
            $tenant, $store, $this->requestReturn($tenant, $store, $order, 1), 'Customer withdrew.',
        );
        $this->assertSame(ReturnStatus::Cancelled, $cancelled->status);

        $approved = app(ApproveReturn::class)->handle($tenant, $store, $this->requestReturn($tenant, $store, $order, 1));
        $cancelledApproved = app(CancelReturn::class)->handle($tenant, $store, $approved, 'Package not sent.');
        $this->assertSame(ReturnStatus::Cancelled, $cancelledApproved->status);
    }

    public function test_receipt_and_inspection_do_not_change_stock_and_damaged_scrap_never_auto_restocks(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order, , , , $level] = $this->fulfilledOrder($tenant, $store = $this->createStore($tenant));
        $return = $this->requestReturn($tenant, $store, $order, 2);
        $before = [$level->on_hand, $level->reserved];
        $received = $this->receiveReturn($tenant, $store, $return, 2);
        $this->assertSame($before, [$level->fresh()->on_hand, $level->fresh()->reserved]);

        $this->inspectReturnItem(
            $tenant, $store, $received, $return->items()->sole(), 2,
            ReturnItemCondition::Damaged, ReturnItemDisposition::Scrap,
        );
        $this->assertSame($before, [$level->fresh()->on_hand, $level->fresh()->reserved]);
        app(CompleteReturn::class)->handle($tenant, $store, $return->fresh());
        $this->assertSame($before, [$level->fresh()->on_hand, $level->fresh()->reserved]);
        $this->assertSame(0, InventoryMovement::query()->where('type', InventoryMovementType::ReturnRestock)->count());
    }

    public function test_restock_targets_chosen_active_tenant_location_and_prevents_over_or_double_restock(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $originalLevel] = $this->fulfilledOrder($tenant, $store = $this->createStore($tenant), 3, actor: $actor);
        $return = $this->requestReturn($tenant, $store, $order, 3, $actor);
        $received = $this->receiveReturn($tenant, $store, $return, 3, $actor);
        $item = $this->inspectReturnItem($tenant, $store, $received, $return->items()->sole(), 3, actor: $actor);
        $location = app(CreateInventoryLocation::class)->handle($tenant, [
            'name' => 'Returns '.Str::random(6), 'code' => 'RET-'.Str::upper(Str::random(6)), 'status' => 'ACTIVE',
        ]);
        $originalBefore = [$originalLevel->fresh()->on_hand, $originalLevel->fresh()->reserved];

        app(RestockReturnItem::class)->handle($tenant, $store, $return->fresh(), $item, $location, 2, $actor);
        $chosenLevel = $location->levels()->where('product_variant_id', $item->product_variant_id)->sole();
        $this->assertSame([2, 0], [$chosenLevel->on_hand, $chosenLevel->reserved]);
        $this->assertSame($originalBefore, [$originalLevel->fresh()->on_hand, $originalLevel->fresh()->reserved]);
        $this->assertSame(2, $item->fresh()->restocked_quantity);

        $movement = InventoryMovement::query()->where('type', InventoryMovementType::ReturnRestock)->sole();
        $this->assertSame($location->getKey(), $movement->inventory_location_id);
        $this->assertSame([2, 0, 2], [$movement->quantity_delta, $movement->quantity_before, $movement->quantity_after]);
        $this->assertSame(['RETURN_ITEM', $item->getKey(), $return->getKey()], [
            $movement->reference_type, $movement->reference_id, $movement->correlation_id,
        ]);

        try {
            app(RestockReturnItem::class)->handle($tenant, $store, $return->fresh(), $item->fresh(), $location, 2);
            $this->fail('Restock exceeded the remaining accepted quantity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        app(RestockReturnItem::class)->handle($tenant, $store, $return->fresh(), $item->fresh(), $location, 1);
        $this->assertSame([3, 0], [$chosenLevel->fresh()->on_hand, $chosenLevel->fresh()->reserved]);

        try {
            app(RestockReturnItem::class)->handle($tenant, $store, $return->fresh(), $item->fresh(), $location, 1);
            $this->fail('A fully restocked item was restocked twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
    }
}
