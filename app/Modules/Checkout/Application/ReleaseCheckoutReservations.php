<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Inventory\Application\InventoryLevelLocker;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Tenancy\Domain\Models\Tenant;

class ReleaseCheckoutReservations
{
    public function __construct(private readonly InventoryLevelLocker $levels) {}

    /** Must run inside the transaction that owns the locked checkout row. */
    public function handle(
        Tenant $tenant,
        CheckoutSession $checkout,
        InventoryReservationStatus $terminalStatus = InventoryReservationStatus::Released,
    ): void {
        $lineIds = $checkout->lines()->pluck('id');
        if ($lineIds->isEmpty()) {
            return;
        }
        $reservations = InventoryReservation::query()->where('tenant_id', $tenant->getKey())
            ->where('reference_type', CheckoutInventoryAllocator::REFERENCE_TYPE)->whereIn('reference_id', $lineIds)
            ->where('status', InventoryReservationStatus::Active)->orderBy('id')->lockForUpdate()->get();
        if ($reservations->isEmpty()) {
            return;
        }

        $levels = $this->levels->lockMany($tenant, $reservations->map(
            fn (InventoryReservation $reservation): array => [$reservation->inventory_location_id, $reservation->product_variant_id],
        )->all())->keyBy(fn (InventoryLevel $level): string => $level->inventory_location_id.':'.$level->product_variant_id);

        foreach ($reservations as $reservation) {
            $level = $levels[$reservation->inventory_location_id.':'.$reservation->product_variant_id];
            $level->reserved -= $reservation->remaining_quantity;
            $level->save();
            $reservation->update($terminalStatus === InventoryReservationStatus::Expired
                ? ['status' => $terminalStatus, 'expired_at' => now()]
                : ['status' => $terminalStatus, 'released_at' => now()]);
        }
    }
}
