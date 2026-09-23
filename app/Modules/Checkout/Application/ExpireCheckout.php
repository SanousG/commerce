<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Checkout\Domain\Enums\CheckoutStatus;
use App\Modules\Checkout\Domain\Models\CheckoutSession;
use App\Modules\Inventory\Domain\Enums\InventoryReservationStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class ExpireCheckout
{
    public function __construct(private readonly ReleaseCheckoutReservations $release) {}

    public function handle(Tenant $tenant, CheckoutSession $checkout): CheckoutSession
    {
        return DB::transaction(function () use ($tenant, $checkout): CheckoutSession {
            $scoped = CheckoutSession::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($checkout->getKey());
            if ($scoped->status === CheckoutStatus::Expired) {
                return $scoped;
            }
            if ($scoped->status !== CheckoutStatus::Ready || $scoped->expires_at === null || $scoped->expires_at->isFuture()) {
                return $scoped;
            }
            $this->release->handle($tenant, $scoped, InventoryReservationStatus::Expired);
            $scoped->update(['status' => CheckoutStatus::Expired]);

            return $scoped->fresh();
        });
    }
}
