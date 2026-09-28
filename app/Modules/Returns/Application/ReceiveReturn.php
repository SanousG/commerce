<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnItem;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveReturn
{
    public function __construct(private readonly ReturnTransition $transition) {}

    /** @param list<array{return_item_id: string, quantity: int}> $items */
    public function handle(Tenant $tenant, Store $store, ReturnRequest $return, array $items, ?User $actor = null): ReturnRequest
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one received item is required.']);
        }

        return DB::transaction(function () use ($tenant, $store, $return, $items, $actor): ReturnRequest {
            $locked = ReturnRequest::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($return->getKey());
            if ($locked->status !== ReturnStatus::Approved) {
                throw ValidationException::withMessages(['return' => 'Only an approved return can be received.']);
            }
            $ids = collect($items)->pluck('return_item_id');
            if ($ids->unique()->count() !== $ids->count()) {
                throw ValidationException::withMessages(['items' => 'Each return item may appear only once.']);
            }
            $returnItems = ReturnItem::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('return_id', $locked->getKey())
                ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($returnItems->count() !== $ids->count()) {
                throw ValidationException::withMessages(['items' => 'A received item is outside this return, store, or tenant.']);
            }
            if ($returnItems->count() !== $locked->items()->count()) {
                throw ValidationException::withMessages(['items' => 'Every return item must be included when receiving the return.']);
            }
            foreach ($items as $input) {
                $item = $returnItems[$input['return_item_id']];
                if ($input['quantity'] <= 0 || $input['quantity'] > $item->requested_quantity) {
                    throw ValidationException::withMessages(['quantity' => 'Received quantity must be within the requested quantity.']);
                }
                $item->update(['received_quantity' => $input['quantity']]);
            }

            return $this->transition->locked(
                $tenant, $store, $locked, [ReturnStatus::Approved], ReturnStatus::Received,
                $actor, attributes: ['received_at' => now()],
            )->fresh(['items', 'history']);
        });
    }
}
