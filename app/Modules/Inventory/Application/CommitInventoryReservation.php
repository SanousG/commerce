<?php

namespace App\Modules\Inventory\Application;

use App\Models\User;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitInventoryReservation
{
    public function __construct(
        private readonly InventoryLevelLocker $levels,
        private readonly InventoryWriter $writer,
    ) {}

    public function handle(Tenant $tenant, InventoryReservation $reservation, ?User $actor = null): InventoryReservation
    {
        return DB::transaction(function () use ($tenant, $reservation, $actor): InventoryReservation {
            $scoped = $tenant->inventoryReservations()->lockForUpdate()->findOrFail($reservation->getKey());
            if ($scoped->status !== InventoryReservationStatus::Active) {
                throw ValidationException::withMessages(['reservation' => 'Only an active reservation can be committed.']);
            }

            $level = $this->levels->lock($tenant, $scoped->inventory_location_id, $scoped->product_variant_id);
            $this->writer->change(
                $level,
                -$scoped->quantity,
                -$scoped->quantity,
                InventoryMovementType::ReservationCommit,
                $actor,
                referenceType: 'RESERVATION',
                referenceId: $scoped->getKey(),
                correlationId: $scoped->getKey(),
            );
            $scoped->update(['status' => InventoryReservationStatus::Committed, 'committed_at' => now()]);

            return $scoped;
        });
    }
}
