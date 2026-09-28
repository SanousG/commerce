<?php

namespace App\Modules\Returns\Application;

use App\Modules\Returns\Domain\Enums\ExchangeStatus;
use App\Modules\Returns\Domain\Models\Exchange;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Validation\ValidationException;

class CancelExchange
{
    public function __construct(private readonly ExchangeTransition $transition) {}

    public function handle(Tenant $tenant, Store $store, Exchange $exchange, string $reason): Exchange
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A cancellation reason is required.']);
        }

        return $this->transition->handle(
            $tenant, $store, $exchange, [ExchangeStatus::Pending, ExchangeStatus::Approved], ExchangeStatus::Cancelled,
            ['cancelled_at' => now(), 'cancellation_reason' => $reason],
        );
    }
}
