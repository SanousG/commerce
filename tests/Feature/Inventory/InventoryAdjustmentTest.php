<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Inventory\Application\AdjustInventory;
use App\Modules\Inventory\Application\ReserveInventory;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentTest extends InventoryFeatureTestCase
{
    public function test_adjustments_create_level_and_auditable_before_after_movements(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant);
        $action = app(AdjustInventory::class);

        $first = $action->handle($tenant, $location, $variant, 12, $actor, 'Opening count');
        $second = $action->handle($tenant, $location, $variant, -5, $actor, 'Damaged');
        $level = InventoryLevel::query()->sole();

        $this->assertSame(7, $level->on_hand);
        $this->assertSame(0, $level->reserved);
        $this->assertSame(7, $level->available);
        $this->assertSame(InventoryMovementType::AdjustmentIn, $first->type);
        $this->assertSame([0, 12, 12], [$first->quantity_before, $first->quantity_after, $first->quantity_delta]);
        $this->assertSame(InventoryMovementType::AdjustmentOut, $second->type);
        $this->assertSame([12, 7, -5], [$second->quantity_before, $second->quantity_after, $second->quantity_delta]);
        $this->assertSame($actor->getKey(), $second->actor_user_id);
        $this->assertSame('Damaged', $second->reason);
    }

    public function test_adjustment_cannot_reduce_on_hand_below_reserved_and_rolls_back_movement(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant);
        $this->stock($tenant, $location, $variant, 10, $actor);
        app(ReserveInventory::class)->handle($tenant, $location, $variant, 4);

        try {
            app(AdjustInventory::class)->handle($tenant, $location, $variant, -7, $actor, 'Invalid shrink');
            $this->fail('Adjustment below reserved stock should fail.');
        } catch (ValidationException) {
            $level = InventoryLevel::query()->sole();
            $this->assertSame([10, 4, 6], [$level->on_hand, $level->reserved, $level->available]);
            $this->assertSame(1, $tenant->inventoryMovements()->count());
        }
    }

    public function test_sequential_stale_inputs_are_revalidated_against_locked_current_level(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant);
        $this->stock($tenant, $location, $variant, 10, $actor);
        $staleLocation = $location->fresh();
        $staleVariant = $variant->fresh();
        app(AdjustInventory::class)->handle($tenant, $location, $variant, -8, $actor, 'First writer');

        $this->expectException(ValidationException::class);
        app(AdjustInventory::class)->handle($tenant, $staleLocation, $staleVariant, -3, $actor, 'Stale writer');
    }

    public function test_inactive_location_zero_quantity_and_cross_tenant_inputs_are_rejected(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $location = $this->createLocation($tenant, ['status' => 'INACTIVE']);
        $variant = $this->createVariant($tenant);

        try {
            app(AdjustInventory::class)->handle($tenant, $location, $variant, 1, $actor, 'No');
            $this->fail('Inactive location should fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_levels', 0);
        }

        try {
            app(AdjustInventory::class)->handle($tenant, $tenant->defaultInventoryLocation()->sole(), $variant, 0, $actor, 'No');
            $this->fail('Zero adjustment should fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_movements', 0);
        }

        $foreignVariant = $this->createVariant($this->createTenantFor(User::factory()->create()));
        $this->expectException(ModelNotFoundException::class);
        app(AdjustInventory::class)->handle(
            $tenant,
            $tenant->defaultInventoryLocation()->sole(),
            $foreignVariant,
            1,
            $actor,
            'Cross tenant',
        );
    }

    public function test_database_rejects_invalid_level_quantities(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);

        $this->expectException(QueryException::class);
        InventoryLevel::create([
            'tenant_id' => $tenant->getKey(),
            'inventory_location_id' => $tenant->defaultInventoryLocation()->sole()->getKey(),
            'product_variant_id' => $variant->getKey(),
            'on_hand' => 2,
            'reserved' => 3,
        ]);
    }
}
