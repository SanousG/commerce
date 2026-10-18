<?php

namespace App\Modules\Costing\Application;

use App\Models\User;
use App\Modules\Costing\Domain\Enums\InventoryCostMovementType;
use App\Modules\Costing\Domain\Models\InventoryCostMovement;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Models\FulfillmentItem;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Returns\Domain\Models\ReturnItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class InventoryCosting
{
    public function __construct(private readonly InventoryValuationLocker $valuations) {}

    /** @param Collection<int, InventoryLevel> $levels */
    public function lockValuations(Collection $levels): void
    {
        $this->valuations->lockMany($levels);
    }

    public function receive(
        InventoryLevel $level,
        InventoryMovement $inventoryMovement,
        string $unitCost,
        string $currency,
        string $referenceId,
        string $correlationId,
        ?User $actor,
    ): InventoryCostMovement {
        return $this->increase(
            $level,
            $inventoryMovement,
            $inventoryMovement->quantity_before,
            $inventoryMovement->quantity_delta,
            $unitCost,
            $currency,
            InventoryCostMovementType::PurchaseReceipt,
            'PURCHASE_RECEIPT_ITEM',
            $referenceId,
            $correlationId,
            $actor,
        );
    }

    public function fulfill(
        InventoryLevel $level,
        InventoryMovement $inventoryMovement,
        FulfillmentItem $item,
        string $correlationId,
        ?User $actor,
    ): InventoryCostMovement {
        $valuation = $this->valuations->lock($level);
        [$average, $currency] = $this->requireActive($valuation->average_unit_cost, $valuation->currency);
        $cogs = BigDecimal::of($average)->multipliedBy($item->quantity)->toScale(4, RoundingMode::HalfUp);

        $item->update([
            'unit_cost_snapshot' => $average,
            'cogs_amount' => (string) $cogs,
            'cost_currency' => $currency,
        ]);

        return $this->movement(
            $level,
            $inventoryMovement,
            InventoryCostMovementType::FulfillmentCogs,
            -$item->quantity,
            $average,
            '-'.(string) $cogs,
            $average,
            $average,
            $currency,
            'FULFILLMENT_ITEM',
            $item->getKey(),
            $correlationId,
            $actor,
        );
    }

    public function restockReturn(
        InventoryLevel $level,
        InventoryMovement $inventoryMovement,
        ReturnItem $item,
        int $quantity,
        string $correlationId,
        ?User $actor,
    ): InventoryCostMovement {
        $costRows = FulfillmentItem::query()
            ->join('fulfillments', 'fulfillments.id', '=', 'fulfillment_items.fulfillment_id')
            ->where('fulfillment_items.tenant_id', $item->tenant_id)
            ->where('fulfillment_items.store_id', $item->store_id)
            ->where('fulfillment_items.order_item_id', $item->order_item_id)
            ->where('fulfillments.status', FulfillmentStatus::Fulfilled->value)
            ->get(['fulfillment_items.quantity', 'fulfillment_items.unit_cost_snapshot', 'fulfillment_items.cost_currency']);

        if ($costRows->isEmpty() || $costRows->contains(fn ($row): bool => $row->unit_cost_snapshot === null)) {
            $this->notInitialized('The returned units do not have complete historical fulfillment costs.');
        }
        $currencies = $costRows->pluck('cost_currency')->unique();
        if ($currencies->count() !== 1) {
            $this->currencyMismatch();
        }
        $fulfilledQuantity = (int) $costRows->sum('quantity');
        $returnCost = $costRows->reduce(
            fn (BigDecimal $total, $row): BigDecimal => $total->plus(BigDecimal::of($row->unit_cost_snapshot)->multipliedBy($row->quantity)),
            BigDecimal::zero(),
        )->dividedBy($fulfilledQuantity, 6, RoundingMode::HalfUp);

        return $this->increase(
            $level,
            $inventoryMovement,
            $inventoryMovement->quantity_before,
            $quantity,
            (string) $returnCost,
            (string) $currencies->first(),
            InventoryCostMovementType::ReturnRestock,
            'RETURN_ITEM',
            $item->getKey(),
            $correlationId,
            $actor,
        );
    }

    private function increase(
        InventoryLevel $level,
        InventoryMovement $inventoryMovement,
        int $oldQuantity,
        int $quantity,
        string $unitCost,
        string $currency,
        InventoryCostMovementType $type,
        string $referenceType,
        string $referenceId,
        string $correlationId,
        ?User $actor,
    ): InventoryCostMovement {
        $valuation = $this->valuations->lock($level);
        $currency = strtoupper($currency);
        if ($valuation->currency !== null && $valuation->currency !== $currency) {
            $this->currencyMismatch();
        }
        if ($oldQuantity > 0 && $valuation->average_unit_cost === null) {
            $this->notInitialized();
        }

        $before = $valuation->average_unit_cost;
        $incoming = BigDecimal::of($unitCost);
        $average = $oldQuantity === 0
            ? $incoming->toScale(6, RoundingMode::HalfUp)
            : BigDecimal::of($before)->multipliedBy($oldQuantity)
                ->plus($incoming->multipliedBy($quantity))
                ->dividedBy($oldQuantity + $quantity, 6, RoundingMode::HalfUp);
        $valuation->update([
            'average_unit_cost' => (string) $average,
            'currency' => $currency,
            'version' => $valuation->version + 1,
        ]);
        $delta = $incoming->multipliedBy($quantity)->toScale(4, RoundingMode::HalfUp);

        return $this->movement(
            $level, $inventoryMovement, $type, $quantity, (string) $incoming->toScale(6), (string) $delta,
            $before, (string) $average, $currency, $referenceType, $referenceId, $correlationId, $actor,
        );
    }

    private function movement(
        InventoryLevel $level,
        InventoryMovement $inventoryMovement,
        InventoryCostMovementType $type,
        int $quantity,
        string $unitCost,
        string $valueDelta,
        ?string $averageBefore,
        string $averageAfter,
        string $currency,
        string $referenceType,
        string $referenceId,
        string $correlationId,
        ?User $actor,
    ): InventoryCostMovement {
        return InventoryCostMovement::create([
            'tenant_id' => $level->tenant_id,
            'inventory_location_id' => $level->inventory_location_id,
            'product_variant_id' => $level->product_variant_id,
            'inventory_movement_id' => $inventoryMovement->getKey(),
            'movement_type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'value_delta' => $valueDelta,
            'average_cost_before' => $averageBefore,
            'average_cost_after' => $averageAfter,
            'currency' => $currency,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'correlation_id' => $correlationId,
            'actor_user_id' => $actor?->getKey(),
            'occurred_at' => $inventoryMovement->occurred_at,
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function requireActive(?string $average, ?string $currency): array
    {
        if ($average === null || $currency === null) {
            $this->notInitialized();
        }

        return [$average, $currency];
    }

    private function notInitialized(string $detail = 'Inventory cost must be initialized before this operation.'): never
    {
        throw ValidationException::withMessages(['inventory_cost' => "INVENTORY_COST_NOT_INITIALIZED: {$detail}"]);
    }

    private function currencyMismatch(): never
    {
        throw ValidationException::withMessages(['inventory_cost' => 'INVENTORY_COST_CURRENCY_MISMATCH: FX conversion is not available.']);
    }
}
