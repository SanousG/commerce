<?php

namespace App\Modules\Inventory\Application;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Costing\Application\InventoryCosting;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveInventory
{
    public function __construct(
        private readonly InventoryLevelLocker $levels,
        private readonly InventoryWriter $writer,
        private readonly InventoryCosting $costing,
    ) {}

    /**
     * @param  list<array{product_variant_id: string, quantity: int, unit_cost: string, currency: string, reference_id: string}>  $items
     * @return Collection<int, InventoryMovement>
     */
    public function handle(Tenant $tenant, InventoryLocation $location, array $items, string $correlationId, ?User $actor = null): Collection
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one inventory receipt item is required.']);
        }

        return DB::transaction(function () use ($tenant, $location, $items, $correlationId, $actor): Collection {
            $location = InventoryLocation::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($location->getKey());
            if ($location->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['inventory_location_id' => 'Inventory can only be received at an active tenant location.']);
            }

            $items = collect($items)->sortBy('product_variant_id')->values();
            if ($items->contains(fn (array $item): bool => $item['quantity'] <= 0)
                || $items->pluck('reference_id')->unique()->count() !== $items->count()) {
                throw ValidationException::withMessages(['items' => 'Receipt references must be unique and quantities must be positive.']);
            }
            $variantIds = $items->pluck('product_variant_id')->unique()->values();
            $variants = ProductVariant::query()->where('tenant_id', $tenant->getKey())
                ->whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($variants->count() !== $variantIds->count()) {
                throw ValidationException::withMessages(['items' => 'An inventory receipt variant is outside the tenant.']);
            }

            $levels = $this->levels->lockMany($tenant, $variantIds
                ->map(fn (string $variantId): array => [$location->getKey(), $variantId])->all())
                ->keyBy('product_variant_id');

            return $items->map(function (array $item) use ($levels, $actor, $correlationId) {
                $level = $levels[$item['product_variant_id']];
                $movement = $this->writer->change(
                    $level, $item['quantity'], 0, InventoryMovementType::PurchaseReceipt, $actor,
                    referenceType: 'PURCHASE_RECEIPT_ITEM', referenceId: $item['reference_id'], correlationId: $correlationId,
                );
                $this->costing->receive(
                    $level, $movement, $item['unit_cost'], $item['currency'], $item['reference_id'], $correlationId, $actor,
                );

                return $movement;
            });
        });
    }
}
