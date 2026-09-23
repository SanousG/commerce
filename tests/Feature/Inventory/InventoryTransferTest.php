<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Inventory\Application\ReserveInventory;
use App\Modules\Inventory\Application\TransferInventory;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InventoryTransferTest extends InventoryFeatureTestCase
{
    public function test_multi_line_transfer_moves_available_stock_and_records_correlated_movements(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $source = $tenant->defaultInventoryLocation()->sole();
        $destination = $this->createLocation($tenant);
        $first = $this->createVariant($tenant, 'TRANSFER-A');
        $second = $this->createVariant($tenant, 'TRANSFER-B');
        $this->stock($tenant, $source, $first, 10, $actor);
        $this->stock($tenant, $source, $second, 20, $actor);
        app(ReserveInventory::class)->handle($tenant, $source, $first, 4);

        $transfer = app(TransferInventory::class)->handle($tenant, $source, $destination, [
            ['product_variant_id' => $first->getKey(), 'quantity' => 6],
            ['product_variant_id' => $second->getKey(), 'quantity' => 7],
        ], $actor, 'Rebalance');

        $this->assertCount(2, $transfer->lines);
        $this->assertSame(4, InventoryLevel::query()->whereBelongsTo($source, 'location')->whereBelongsTo($first, 'variant')->sole()->on_hand);
        $this->assertSame(0, InventoryLevel::query()->whereBelongsTo($source, 'location')->whereBelongsTo($first, 'variant')->sole()->available);
        $this->assertSame(6, InventoryLevel::query()->whereBelongsTo($destination, 'location')->whereBelongsTo($first, 'variant')->sole()->on_hand);
        $movements = $tenant->inventoryMovements()->where('correlation_id', $transfer->getKey())->get();
        $this->assertCount(4, $movements);
        $this->assertTrue($movements->every(fn ($movement) => $movement->reference_type === 'TRANSFER'
            && $movement->reference_id === $transfer->getKey()));
        $this->assertSame(2, $movements->where('type', InventoryMovementType::TransferOut)->count());
        $this->assertSame(2, $movements->where('type', InventoryMovementType::TransferIn)->count());
        $this->assertTrue($movements->every(fn ($movement) => $movement->actor_user_id === $actor->getKey()));
    }

    public function test_transfer_validates_every_line_before_writing_any_transfer_data(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $source = $tenant->defaultInventoryLocation()->sole();
        $destination = $this->createLocation($tenant);
        $first = $this->createVariant($tenant);
        $second = $this->createVariant($tenant);
        $this->stock($tenant, $source, $first, 10, $actor);
        $this->stock($tenant, $source, $second, 2, $actor);
        $movementCount = $tenant->inventoryMovements()->count();

        try {
            app(TransferInventory::class)->handle($tenant, $source, $destination, [
                ['product_variant_id' => $first->getKey(), 'quantity' => 5],
                ['product_variant_id' => $second->getKey(), 'quantity' => 3],
            ], $actor);
            $this->fail('Insufficient line should fail the whole transfer.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_transfers', 0);
            $this->assertDatabaseCount('inventory_transfer_lines', 0);
            $this->assertSame($movementCount, $tenant->inventoryMovements()->count());
            $this->assertSame(10, $tenant->inventoryLevels()->where('product_variant_id', $first->getKey())->sole()->on_hand);
            $this->assertFalse($tenant->inventoryLevels()->where('inventory_location_id', $destination->getKey())->exists());
        }
    }

    public function test_transfer_rejects_reserved_stock_duplicate_lines_and_same_location(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $source = $tenant->defaultInventoryLocation()->sole();
        $destination = $this->createLocation($tenant);
        $variant = $this->createVariant($tenant);
        $this->stock($tenant, $source, $variant, 10, $actor);
        app(ReserveInventory::class)->handle($tenant, $source, $variant, 7);
        $action = app(TransferInventory::class);

        foreach ([
            [$source, $destination, [['product_variant_id' => $variant->getKey(), 'quantity' => 4]]],
            [$source, $destination, [
                ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
                ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
            ]],
            [$source, $source, [['product_variant_id' => $variant->getKey(), 'quantity' => 1]]],
        ] as [$from, $to, $lines]) {
            try {
                $action->handle($tenant, $from, $to, $lines, $actor);
                $this->fail('Invalid transfer should fail.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('inventory_transfers', 0);
            }
        }
    }

    public function test_transfer_rejects_inactive_and_cross_tenant_locations_and_variants(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $source = $tenant->defaultInventoryLocation()->sole();
        $inactive = $this->createLocation($tenant, ['status' => 'INACTIVE']);
        $variant = $this->createVariant($tenant);
        $this->stock($tenant, $source, $variant, 2, $actor);

        try {
            app(TransferInventory::class)->handle($tenant, $source, $inactive, [
                ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
            ], $actor);
            $this->fail('Inactive location should fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_transfers', 0);
        }

        $other = $this->createTenantFor(User::factory()->create());
        try {
            app(TransferInventory::class)->handle($tenant, $source, $other->defaultInventoryLocation()->sole(), [
                ['product_variant_id' => $variant->getKey(), 'quantity' => 1],
            ], $actor);
            $this->fail('Foreign location should be hidden.');
        } catch (NotFoundHttpException) {
            $this->assertDatabaseCount('inventory_transfers', 0);
        }

        $this->expectException(ValidationException::class);
        app(TransferInventory::class)->handle($tenant, $source, $this->createLocation($tenant), [
            ['product_variant_id' => $this->createVariant($other)->getKey(), 'quantity' => 1],
        ], $actor);
    }
}
