<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelCheckout
{
    public function __construct(private readonly ReleaseCheckoutReservations $release) {}

    public function handle(Tenant $tenant, CheckoutSession $checkout): CheckoutSession
    {
        return DB::transaction(function () use ($tenant, $checkout): CheckoutSession {
            $scoped = CheckoutSession::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($checkout->getKey());
            if ($scoped->status === CheckoutStatus::Cancelled) {
                return $scoped;
            }
            if (! in_array($scoped->status, [CheckoutStatus::Pending, CheckoutStatus::Ready], true)) {
                throw ValidationException::withMessages(['checkout' => 'This checkout cannot be cancelled.']);
            }
            $this->release->handle($tenant, $scoped);
            $scoped->update(['status' => CheckoutStatus::Cancelled]);

            return $scoped->fresh();
        });
    }
}
