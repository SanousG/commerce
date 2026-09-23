<?php

namespace App\Modules\Inventory\Application;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Enums\InventoryTransferStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryTransfer;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferInventory
{
    public function __construct(
        private readonly InventoryLevelLocker $levels,
        private readonly InventoryWriter $writer,
    ) {}

    /** @param list<array{product_variant_id: string, quantity: int}> $lines */
    public function handle(
        Tenant $tenant,
        InventoryLocation $source,
        InventoryLocation $destination,
        array $lines,
        User $actor,
        ?string $notes = null,
    ): InventoryTransfer {
        return DB::transaction(function () use ($tenant, $source, $destination, $lines, $actor, $notes): InventoryTransfer {
            if ($source->getKey() === $destination->getKey()) {
                throw ValidationException::withMessages(['destination_location_id' => 'Transfer locations must be different.']);
            }

            $locations = $tenant->inventoryLocations()
                ->whereKey([$source->getKey(), $destination->getKey()])
                ->orderBy('id')->lockForUpdate()->get()->keyBy(fn (InventoryLocation $location) => $location->getKey());
            $scopedSource = $locations->get($source->getKey());
            $scopedDestination = $locations->get($destination->getKey());
            abort_if($scopedSource === null || $scopedDestination === null, 404);

            if ($scopedSource->status !== InventoryLocationStatus::Active
                || $scopedDestination->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['location' => 'Transfers require two active inventory locations.']);
            }

            $quantities = [];
            foreach ($lines as $line) {
                $variantId = $line['product_variant_id'];
                $quantity = $line['quantity'];
                if ($quantity <= 0 || isset($quantities[$variantId])) {
                    throw ValidationException::withMessages(['lines' => 'Transfer variants must be unique with positive quantities.']);
                }

                $quantities[$variantId] = $quantity;
            }
            $quantities = collect($quantities);
            if ($quantities->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'A transfer requires positive quantities.']);
            }

            $variantIds = $quantities->keys()->sort()->values();
            $variants = ProductVariant::query()->where('tenant_id', $tenant->getKey())
                ->whereKey($variantIds)->orderBy('id')->lockForUpdate()->get();
            if ($variants->count() !== $variantIds->count()) {
                throw ValidationException::withMessages(['lines' => 'A selected product variant is invalid.']);
            }

            $pairs = $variantIds->flatMap(fn (string $variantId): array => [
                [$scopedSource->getKey(), $variantId],
                [$scopedDestination->getKey(), $variantId],
            ])->all();
            $lockedLevels = $this->levels->lockMany($tenant, $pairs)->keyBy(
                fn (InventoryLevel $level): string => $level->inventory_location_id.':'.$level->product_variant_id,
            );

            foreach ($variantIds as $variantId) {
                $level = $lockedLevels->get($scopedSource->getKey().':'.$variantId);
                if ($level === null || $level->available < $quantities[$variantId]) {
                    throw ValidationException::withMessages(['lines' => 'A source inventory level has insufficient available quantity.']);
                }
            }

            $transfer = InventoryTransfer::create([
                'tenant_id' => $tenant->getKey(),
                'source_location_id' => $scopedSource->getKey(),
                'destination_location_id' => $scopedDestination->getKey(),
                'status' => InventoryTransferStatus::Completed,
                'created_by' => $actor->getKey(),
                'notes' => $notes,
                'completed_at' => now(),
            ]);

            foreach ($variantIds as $variantId) {
                $quantity = $quantities[$variantId];
                $transfer->lines()->create([
                    'tenant_id' => $tenant->getKey(),
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                ]);
                $this->writer->change(
                    $lockedLevels[$scopedSource->getKey().':'.$variantId],
                    -$quantity,
                    0,
                    InventoryMovementType::TransferOut,
                    $actor,
                    $notes,
                    referenceType: 'TRANSFER',
                    referenceId: $transfer->getKey(),
                    correlationId: $transfer->getKey(),
                );
                $this->writer->change(
                    $lockedLevels[$scopedDestination->getKey().':'.$variantId],
                    $quantity,
                    0,
                    InventoryMovementType::TransferIn,
                    $actor,
                    $notes,
                    referenceType: 'TRANSFER',
                    referenceId: $transfer->getKey(),
                    correlationId: $transfer->getKey(),
                );
            }

            return $transfer->load('lines');
        });
    }
}
