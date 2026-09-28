<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Returns\Domain\Enums\ExchangeStatus;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\Exchange;
use App\Modules\Returns\Domain\Models\ExchangeItem;
use App\Modules\Returns\Domain\Models\ReturnItem;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateExchange
{
    /** @param list<array{return_item_id: string, replacement_product_variant_id: string, quantity: int}> $items */
    public function handle(
        Tenant $tenant,
        Store $store,
        ReturnRequest $return,
        array $items,
        ?User $actor = null,
    ): Exchange {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'An exchange requires at least one item.']);
        }

        return DB::transaction(function () use ($tenant, $store, $return, $items, $actor): Exchange {
            $lockedReturn = ReturnRequest::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($return->getKey());
            if (! in_array($lockedReturn->status, [ReturnStatus::Inspecting, ReturnStatus::Completed], true)) {
                throw ValidationException::withMessages(['return' => 'Exchanges require inspected accepted return items.']);
            }
            $ids = collect($items)->pluck('return_item_id');
            if ($ids->unique()->count() !== $ids->count()) {
                throw ValidationException::withMessages(['items' => 'Each return item may appear only once.']);
            }
            $returnItems = ReturnItem::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('return_id', $lockedReturn->getKey())
                ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($returnItems->count() !== $ids->count()) {
                throw ValidationException::withMessages(['items' => 'An exchange item is outside this return, store, or tenant.']);
            }
            $variantIds = collect($items)->pluck('replacement_product_variant_id');
            $variants = ProductVariant::query()->where('tenant_id', $tenant->getKey())
                ->whereIn('id', $variantIds)->get()->keyBy('id');
            if ($variants->count() !== $variantIds->unique()->count()) {
                throw ValidationException::withMessages(['items' => 'A replacement variant is outside this tenant.']);
            }
            foreach ($items as $input) {
                $item = $returnItems[$input['return_item_id']];
                $allocated = (int) ExchangeItem::query()
                    ->join('exchanges', 'exchanges.id', '=', 'exchange_items.exchange_id')
                    ->where('exchange_items.tenant_id', $tenant->getKey())
                    ->where('exchange_items.store_id', $store->getKey())
                    ->where('exchange_items.return_item_id', $item->getKey())
                    ->where('exchanges.status', '!=', ExchangeStatus::Cancelled)->sum('exchange_items.quantity');
                if ($input['quantity'] <= 0 || $allocated + $input['quantity'] > $item->accepted_quantity) {
                    throw ValidationException::withMessages(['quantity' => 'Exchange quantity exceeds the unallocated accepted quantity.']);
                }
            }

            $exchange = Exchange::query()->create([
                'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(),
                'order_id' => $lockedReturn->order_id, 'return_id' => $lockedReturn->getKey(),
                'status' => ExchangeStatus::Pending, 'created_by_user_id' => $actor?->getKey(),
            ]);
            foreach ($items as $input) {
                $returnItem = $returnItems[$input['return_item_id']];
                $exchange->items()->create([
                    'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(),
                    'return_id' => $lockedReturn->getKey(),
                    'return_item_id' => $returnItem->getKey(),
                    'replacement_product_variant_id' => $input['replacement_product_variant_id'],
                    'quantity' => $input['quantity'],
                ]);
            }

            return $exchange->load('items');
        });
    }
}
