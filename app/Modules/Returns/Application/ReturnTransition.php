<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Validation\ValidationException;

class ReturnTransition
{
    /** @param list<ReturnStatus> $from */
    public function locked(
        Tenant $tenant,
        Store $store,
        ReturnRequest $return,
        array $from,
        ReturnStatus $to,
        ?User $actor = null,
        ?string $reason = null,
        array $attributes = [],
    ): ReturnRequest {
        $locked = ReturnRequest::query()->where('tenant_id', $tenant->getKey())
            ->where('store_id', $store->getKey())->lockForUpdate()->findOrFail($return->getKey());
        if ($locked->status === $to) {
            return $locked;
        }
        if (! in_array($locked->status, $from, true)) {
            throw ValidationException::withMessages(['return' => "The return cannot transition to {$to->value}."]);
        }
        $previous = $locked->status;
        $locked->update(['status' => $to, ...$attributes]);
        $locked->history()->create([
            'tenant_id' => $tenant->getKey(), 'from_status' => $previous, 'to_status' => $to,
            'actor_user_id' => $actor?->getKey(), 'reason' => $reason,
        ]);

        return $locked;
    }
}
