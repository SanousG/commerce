<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectReturn
{
    public function __construct(private readonly ReturnTransition $transition) {}

    public function handle(Tenant $tenant, Store $store, ReturnRequest $return, string $reason, ?User $actor = null): ReturnRequest
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A rejection reason is required.']);
        }

        return DB::transaction(fn () => $this->transition->locked(
            $tenant, $store, $return, [ReturnStatus::Requested], ReturnStatus::Rejected,
            $actor, $reason, ['rejected_at' => now(), 'rejection_reason' => $reason],
        )->fresh(['items', 'history']));
    }
}
