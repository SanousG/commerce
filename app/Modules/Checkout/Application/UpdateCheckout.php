<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCheckout
{
    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, CheckoutSession $checkout, array $attributes): CheckoutSession
    {
        return DB::transaction(function () use ($tenant, $checkout, $attributes): CheckoutSession {
            $scoped = CheckoutSession::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($checkout->getKey());
            if ($scoped->status !== CheckoutStatus::Pending) {
                throw ValidationException::withMessages(['checkout' => 'Only a pending checkout can be edited.']);
            }
            $scoped->update($attributes);

            return $scoped->fresh();
        });
    }
}
