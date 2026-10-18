<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Costing\Application\InventoryCosting;
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
        private readonly InventoryCosting $costing,
        private readonly AuditRecorder $audit,
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
            $movement = $this->writer->change(
                $level, $quantity, 0, InventoryMovementType::ReturnRestock, $actor,
                referenceType: 'RETURN_ITEM', referenceId: $lockedItem->getKey(), correlationId: $lockedReturn->getKey(),
            );
            $costMovement = $this->costing->restockReturn($level, $movement, $lockedItem, $quantity, $lockedReturn->getKey(), $actor);
            $lockedItem->increment('restocked_quantity', $quantity);
            $this->audit->record(
                tenant: $tenant, actorType: $actor === null ? AuditActorType::System : AuditActorType::User,
                action: AuditAction::ReturnItemRestocked, subjectType: AuditSubjectType::ReturnItem,
                subjectId: $lockedItem->getKey(),
                metadata: [
                    'return_id' => $lockedReturn->getKey(),
                    'location_code' => $lockedLocation->code,
                    'quantity' => $quantity,
                    'unit_cost' => $costMovement->unit_cost,
                    'value' => $costMovement->value_delta,
                    'currency' => $costMovement->currency,
                ],
                store: $store, actor: $actor,
                subjectLabel: $lockedItem->order_item_id,
            );

            return $lockedItem->fresh();
        });
    }
}
