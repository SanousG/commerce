<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Inventory\Application\CommitInventoryReservation;
use App\Modules\Inventory\Application\ExpireInventoryReservation;
use App\Modules\Inventory\Application\ReleaseInventoryReservation;
use App\Modules\Inventory\Application\ReserveInventory;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryReservationTest extends InventoryFeatureTestCase
{
    public function test_reserve_reduces_available_and_release_is_idempotent(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant);
        $this->stock($tenant, $location, $variant, 10);
        $referenceId = (string) Str::uuid();
        $reservation = app(ReserveInventory::class)->handle(
            $tenant, $location, $variant, 4, 'order', $referenceId, now()->addHour(),
        );

        $level = InventoryLevel::query()->sole();
        $this->assertSame([10, 4, 6], [$level->on_hand, $level->reserved, $level->available]);
        $this->assertSame('order', $reservation->reference_type);
        $this->assertSame($referenceId, $reservation->reference_id);

        $released = app(ReleaseInventoryReservation::class)->handle($tenant, $reservation);
        $releasedAt = $released->released_at;
        $again = app(ReleaseInventoryReservation::class)->handle($tenant, $reservation);
        $this->assertSame(InventoryReservationStatus::Released, $again->status);
        $this->assertTrue($releasedAt->equalTo($again->released_at));
        $this->assertSame(0, $level->fresh()->reserved);
        $this->assertSame(1, $tenant->inventoryReservations()->count());
    }

    public function test_reservation_uses_current_available_stock_and_validates_reference_pair(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant);
        $this->stock($tenant, $location, $variant, 5);
        $action = app(ReserveInventory::class);
        $action->handle($tenant, $location, $variant, 4);

        try {
            $action->handle($tenant, $location, $variant, 2);
            $this->fail('A stale availability assumption should be revalidated.');
        } catch (ValidationException) {
            $this->assertSame(4, InventoryLevel::query()->sole()->reserved);
            $this->assertSame(1, $tenant->inventoryReservations()->count());
        }

        $this->expectException(ValidationException::class);
        $action->handle($tenant, $location, $variant, 1, 'order');
    }

    public function test_commit_reduces_on_hand_and_reserved_and_records_correlated_movement(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant);
        $this->stock($tenant, $location, $variant, 10, $actor);
        $reservation = app(ReserveInventory::class)->handle($tenant, $location, $variant, 3);

        $committed = app(CommitInventoryReservation::class)->handle($tenant, $reservation, $actor);
        $level = InventoryLevel::query()->sole();
        $movement = $tenant->inventoryMovements()->where('correlation_id', $reservation->getKey())->sole();
        $this->assertSame('RESERVATION', $movement->reference_type);
        $this->assertSame($reservation->getKey(), $movement->reference_id);

        $this->assertSame(InventoryReservationStatus::Committed, $committed->status);
        $this->assertNotNull($committed->committed_at);
        $this->assertSame([7, 0, 7], [$level->on_hand, $level->reserved, $level->available]);
        $this->assertSame(InventoryMovementType::ReservationCommit, $movement->type);
        $this->assertSame([10, 7, -3], [$movement->quantity_before, $movement->quantity_after, $movement->quantity_delta]);
        $this->assertSame($actor->getKey(), $movement->actor_user_id);
    }

    public function test_due_reservation_expires_without_changing_on_hand(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant);
        $this->stock($tenant, $location, $variant, 8);
        $reservation = app(ReserveInventory::class)->handle(
            $tenant, $location, $variant, 3, expiresAt: now()->subMinute(),
        );

        $expired = app(ExpireInventoryReservation::class)->handle($tenant, $reservation);
        $level = InventoryLevel::query()->sole();

        $this->assertSame(InventoryReservationStatus::Expired, $expired->status);
        $this->assertNotNull($expired->expired_at);
        $this->assertSame([8, 0, 8], [$level->on_hand, $level->reserved, $level->available]);
    }

    public function test_terminal_and_not_due_reservation_transitions_are_rejected(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $location = $tenant->defaultInventoryLocation()->sole();
        $variant = $this->createVariant($tenant);
        $this->stock($tenant, $location, $variant, 10);
        $future = app(ReserveInventory::class)->handle($tenant, $location, $variant, 2, expiresAt: now()->addHour());

        try {
            app(ExpireInventoryReservation::class)->handle($tenant, $future);
            $this->fail('Future reservation should not expire.');
        } catch (ValidationException) {
            $this->assertSame(2, InventoryLevel::query()->sole()->reserved);
        }

        app(CommitInventoryReservation::class)->handle($tenant, $future);
        foreach ([ReleaseInventoryReservation::class, CommitInventoryReservation::class, ExpireInventoryReservation::class] as $action) {
            try {
                app($action)->handle($tenant, $future);
                $this->fail('Committed reservation must be terminal.');
            } catch (ValidationException) {
                $this->assertSame(InventoryReservationStatus::Committed, $future->fresh()->status);
            }
        }
    }

    public function test_reservation_services_hide_cross_tenant_models_and_reject_inactive_location(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $locationA = $tenantA->defaultInventoryLocation()->sole();
        $variantA = $this->createVariant($tenantA);
        $this->stock($tenantA, $locationA, $variantA, 3);
        $reservation = app(ReserveInventory::class)->handle($tenantA, $locationA, $variantA, 1);

        try {
            app(ReleaseInventoryReservation::class)->handle($tenantB, $reservation);
            $this->fail('Foreign reservation should be hidden.');
        } catch (ModelNotFoundException) {
            $this->assertSame(1, InventoryLevel::query()->where('tenant_id', $tenantA->getKey())->sole()->reserved);
        }

        $inactive = $this->createLocation($tenantA, ['status' => 'INACTIVE']);
        $this->expectException(ValidationException::class);
        app(ReserveInventory::class)->handle($tenantA, $inactive, $variantA, 1);
    }
}
