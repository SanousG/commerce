<?php

namespace App\Modules\Inventory\Application;

use App\Models\User;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryWriter
{
    public function change(
        InventoryLevel $level,
        int $onHandDelta,
        int $reservedDelta,
        InventoryMovementType $type,
        ?User $actor = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $correlationId = null,
    ): InventoryMovement {
        $before = $level->on_hand;
        $onHand = $this->safeAdd($before, $onHandDelta);
        $reserved = $this->safeAdd($level->reserved, $reservedDelta);

        if ($onHand < 0 || $reserved < 0 || $reserved > $onHand) {
            throw ValidationException::withMessages([
                'quantity' => 'The operation would make inventory unavailable or negative.',
            ]);
        }

        $level->update(['on_hand' => $onHand, 'reserved' => $reserved]);

        return InventoryMovement::create([
            'tenant_id' => $level->tenant_id,
            'inventory_location_id' => $level->inventory_location_id,
            'product_variant_id' => $level->product_variant_id,
            'type' => $type,
            'quantity_delta' => $onHandDelta,
            'quantity_before' => $before,
            'quantity_after' => $onHand,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'correlation_id' => $correlationId ?? (string) Str::uuid7(),
            'reason' => $reason,
            'actor_user_id' => $actor?->getKey(),
            'occurred_at' => now(),
        ]);
    }

    private function safeAdd(int $value, int $delta): int
    {
        if (($delta > 0 && $value > PHP_INT_MAX - $delta)
            || ($delta < 0 && $value < PHP_INT_MIN - $delta)) {
            throw ValidationException::withMessages(['quantity' => 'The quantity is outside the supported range.']);
        }

        return $value + $delta;
    }
}
