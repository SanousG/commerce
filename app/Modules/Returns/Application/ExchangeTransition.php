<?php

namespace App\Modules\Returns\Application;

use App\Modules\Returns\Domain\Enums\ExchangeStatus;
use App\Modules\Returns\Domain\Models\Exchange;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExchangeTransition
{
    /** @param list<ExchangeStatus> $from */
    public function handle(
        Tenant $tenant,
        Store $store,
        Exchange $exchange,
        array $from,
        ExchangeStatus $to,
        array $attributes = [],
    ): Exchange {
        return DB::transaction(function () use ($tenant, $store, $exchange, $from, $to, $attributes): Exchange {
            $returnId = Exchange::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->whereKey($exchange->getKey())->valueOrFail('return_id');
            ReturnRequest::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($returnId);
            $locked = Exchange::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($exchange->getKey());
            if ($locked->status === $to) {
                return $locked->load('items');
            }
            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['exchange' => "The exchange cannot transition to {$to->value}."]);
            }
            $locked->update(['status' => $to, ...$attributes]);

            return $locked->fresh('items');
        });
    }
}
