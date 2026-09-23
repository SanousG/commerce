<?php

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpireInventoryReservation
{
    public function __construct(
        private readonly InventoryLevelLocker $levels,
    ) {}

    public function handle(Tenant $tenant, InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($tenant, $reservation): InventoryReservation {
            $scoped = $tenant->inventoryReservations()->lockForUpdate()->findOrFail($reservation->getKey());
            if ($scoped->status !== InventoryReservationStatus::Active
                || $scoped->expires_at === null
                || $scoped->expires_at->isFuture()) {
                throw ValidationException::withMessages(['reservation' => 'Only a due active reservation can expire.']);
            }

            $level = $this->levels->lock($tenant, $scoped->inventory_location_id, $scoped->product_variant_id);
            $level->update(['reserved' => $level->reserved - $scoped->quantity]);
            $scoped->update(['status' => InventoryReservationStatus::Expired, 'expired_at' => now()]);

            return $scoped;
        });
    }
}
