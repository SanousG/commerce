<?php

namespace App\Modules\Returns\Application;

use App\Models\User;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class ApproveReturn
{
    public function __construct(private readonly ReturnTransition $transition) {}

    public function handle(Tenant $tenant, Store $store, ReturnRequest $return, ?User $actor = null): ReturnRequest
    {
        return DB::transaction(fn () => $this->transition->locked(
            $tenant, $store, $return, [ReturnStatus::Requested], ReturnStatus::Approved,
            $actor, attributes: ['approved_at' => now()],
        )->fresh(['items', 'history']));
    }
}
