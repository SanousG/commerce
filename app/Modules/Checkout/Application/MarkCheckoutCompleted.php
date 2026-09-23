<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkCheckoutCompleted
{
    public function handle(Tenant $tenant, CheckoutSession $checkout): CheckoutSession
    {
        return DB::transaction(function () use ($tenant, $checkout): CheckoutSession {
            $scoped = CheckoutSession::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($checkout->getKey());
            if ($scoped->status === CheckoutStatus::Completed) {
                return $scoped;
            }
            if ($scoped->status !== CheckoutStatus::Ready || $scoped->expires_at?->lte(now())) {
                throw ValidationException::withMessages(['checkout' => 'Only a non-expired ready checkout can be completed.']);
            }
            // Order creation will own reservation commitment; Checkout only freezes the handoff state.
            $scoped->update(['status' => CheckoutStatus::Completed, 'completed_at' => now()]);

            return $scoped->fresh();
        });
    }
}
