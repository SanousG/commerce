<?php

namespace App\Modules\Returns\Application;

use App\Modules\Returns\Domain\Enums\ExchangeStatus;
use App\Modules\Returns\Domain\Models\Exchange;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;

class CompleteExchange
{
    public function __construct(private readonly ExchangeTransition $transition) {}

    public function handle(Tenant $tenant, Store $store, Exchange $exchange): Exchange
    {
        return $this->transition->handle(
            $tenant, $store, $exchange, [ExchangeStatus::Approved], ExchangeStatus::Completed,
            ['completed_at' => now()],
        );
    }
}
