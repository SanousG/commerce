<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnItem;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteReturn
{
    public function __construct(private readonly ReturnTransition $transition) {}

    public function handle(Tenant $tenant, Store $store, ReturnRequest $return, ?User $actor = null): ReturnRequest
    {
        return DB::transaction(function () use ($tenant, $store, $return, $actor): ReturnRequest {
            $locked = ReturnRequest::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($return->getKey());
            if ($locked->status === ReturnStatus::Completed) {
                return $locked->load(['items', 'history']);
            }
            if ($locked->status !== ReturnStatus::Inspecting) {
                throw ValidationException::withMessages(['return' => 'Only an inspecting return can be completed.']);
            }
            $items = ReturnItem::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('return_id', $locked->getKey())
                ->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty() || $items->contains(fn (ReturnItem $item): bool => $item->inspected_at === null)) {
                throw ValidationException::withMessages(['items' => 'Every return item must be inspected before completion.']);
            }
            if ($items->contains(fn (ReturnItem $item): bool => $item->disposition === ReturnItemDisposition::Restock
                && $item->restocked_quantity !== $item->accepted_quantity)) {
                throw ValidationException::withMessages(['items' => 'Every accepted RESTOCK quantity must be restocked before completion.']);
            }

            return $this->transition->locked(
                $tenant, $store, $locked, [ReturnStatus::Inspecting], ReturnStatus::Completed,
                $actor, attributes: ['completed_at' => now()],
            )->fresh(['items', 'history']);
        });
    }
}
