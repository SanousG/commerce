<?php

namespace App\Modules\Orders\Application;

use App\Modules\Inventory\Application\InventoryLevelLocker;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryReservation;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Collection;

class OrderInventoryReservations
{
    public function __construct(private readonly InventoryLevelLocker $levels) {}

    public function active(Tenant $tenant, Order $order, bool $lock = false): Collection
    {
        $lineIds = $order->items()->pluck('checkout_line_id');
        $query = InventoryReservation::query()->where('tenant_id', $tenant->getKey())
            ->where('reference_type', 'CHECKOUT_LINE')->whereIn('reference_id', $lineIds)
            ->where('status', InventoryReservationStatus::Active)->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    /** Must run inside the transaction that owns the locked Order row. */
    public function release(Tenant $tenant, Order $order): void
    {
        $reservations = $this->active($tenant, $order, true);
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
            $reservation->update(['status' => InventoryReservationStatus::Released, 'released_at' => now()]);
        }
    }
}
