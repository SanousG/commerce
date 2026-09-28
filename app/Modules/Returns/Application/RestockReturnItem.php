<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Inventory\Application\InventoryLevelLocker;
use App\Modules\Inventory\Application\InventoryWriter;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnItem;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestockReturnItem
{
    public function __construct(
        private readonly InventoryLevelLocker $levels,
        private readonly InventoryWriter $writer,
    ) {}

    public function handle(
        Tenant $tenant,
        Store $store,
        ReturnRequest $return,
        ReturnItem $item,
        InventoryLocation $location,
        int $quantity,
        ?User $actor = null,
    ): ReturnItem {
        return DB::transaction(function () use ($tenant, $store, $return, $item, $location, $quantity, $actor): ReturnItem {
            $lockedReturn = ReturnRequest::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($return->getKey());
            if ($lockedReturn->status !== ReturnStatus::Inspecting) {
                throw ValidationException::withMessages(['return' => 'Only an inspecting return can be restocked.']);
            }
            $lockedItem = ReturnItem::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('return_id', $lockedReturn->getKey())
                ->lockForUpdate()->findOrFail($item->getKey());
            if ($lockedItem->disposition !== ReturnItemDisposition::Restock || $lockedItem->product_variant_id === null
                || $quantity <= 0 || $quantity > $lockedItem->accepted_quantity - $lockedItem->restocked_quantity) {
                throw ValidationException::withMessages(['quantity' => 'Only the accepted unrestocked balance of a RESTOCK item can be restocked.']);
            }
            $lockedLocation = InventoryLocation::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($location->getKey());
            if ($lockedLocation->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['location' => 'Returns can only be restocked into an active tenant location.']);
            }
            $level = $this->levels->lock($tenant, $lockedLocation->getKey(), $lockedItem->product_variant_id);
            $this->writer->change(
                $level, $quantity, 0, InventoryMovementType::ReturnRestock, $actor,
                referenceType: 'RETURN_ITEM', referenceId: $lockedItem->getKey(), correlationId: $lockedReturn->getKey(),
            );
            $lockedItem->increment('restocked_quantity', $quantity);

            return $lockedItem->fresh();
        });
    }
}
