<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReleaseInventoryReservation
{
    public function __construct(private readonly InventoryLevelLocker $levels) {}

    public function handle(Tenant $tenant, InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($tenant, $reservation): InventoryReservation {
            $scoped = $tenant->inventoryReservations()->lockForUpdate()->findOrFail($reservation->getKey());

            if ($scoped->status === InventoryReservationStatus::Released) {
                return $scoped;
            }

            $this->assertActive($scoped);
            $level = $this->levels->lock($tenant, $scoped->inventory_location_id, $scoped->product_variant_id);
            $level->update(['reserved' => $level->reserved - $scoped->quantity]);
            $scoped->update(['status' => InventoryReservationStatus::Released, 'released_at' => now()]);

            return $scoped;
        });
    }

    private function assertActive(InventoryReservation $reservation): void
    {
        if ($reservation->status !== InventoryReservationStatus::Active) {
            throw ValidationException::withMessages(['reservation' => 'Only an active reservation can be released.']);
        }
    }
}
