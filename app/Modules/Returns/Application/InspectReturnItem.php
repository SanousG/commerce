<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Returns\Domain\Enums\ReturnItemCondition;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnItem;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectReturnItem
{
    public function __construct(
        private readonly ReturnEligibility $eligibility,
        private readonly ReturnTransition $transition,
    ) {}

    public function handle(
        Tenant $tenant,
        Store $store,
        ReturnRequest $return,
        ReturnItem $item,
        ReturnItemCondition $condition,
        ReturnItemDisposition $disposition,
        int $acceptedQuantity,
        ?string $notes = null,
        ?User $actor = null,
    ): ReturnItem {
        return DB::transaction(function () use (
            $tenant, $store, $return, $item, $condition, $disposition, $acceptedQuantity, $notes, $actor,
        ): ReturnItem {
            $lockedReturn = ReturnRequest::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($return->getKey());
            if (! in_array($lockedReturn->status, [ReturnStatus::Received, ReturnStatus::Inspecting], true)) {
                throw ValidationException::withMessages(['return' => 'Only a received return can be inspected.']);
            }
            $lockedItem = ReturnItem::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('return_id', $lockedReturn->getKey())
                ->lockForUpdate()->findOrFail($item->getKey());
            if ($lockedItem->restocked_quantity > 0 || $lockedItem->exchangeItems()->exists()) {
                throw ValidationException::withMessages(['item' => 'Inspection cannot change after restock or exchange allocation.']);
            }
            $orderItem = OrderItem::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('order_id', $lockedReturn->order_id)
                ->lockForUpdate()->findOrFail($lockedItem->order_item_id);
            if ($acceptedQuantity < 0 || $acceptedQuantity > $lockedItem->received_quantity
                || $acceptedQuantity > $this->eligibility->returnableQuantity($tenant, $orderItem, $lockedItem->getKey())) {
                throw ValidationException::withMessages(['accepted_quantity' => 'Accepted quantity exceeds the received or returnable quantity.']);
            }
            if ($lockedReturn->status === ReturnStatus::Received) {
                $this->transition->locked(
                    $tenant, $store, $lockedReturn, [ReturnStatus::Received], ReturnStatus::Inspecting,
                    $actor, attributes: ['inspection_started_at' => now()],
                );
            }
            $lockedItem->update([
                'condition' => $condition, 'disposition' => $disposition,
                'accepted_quantity' => $acceptedQuantity, 'inspection_notes' => $notes,
                'inspected_by_user_id' => $actor?->getKey(), 'inspected_at' => now(),
            ]);

            return $lockedItem->fresh();
        });
    }
}
