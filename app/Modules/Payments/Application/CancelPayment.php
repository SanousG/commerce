<?php

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPayment
{
    public function handle(Tenant $tenant, Store $store, Payment $payment): Payment
    {
        return DB::transaction(function () use ($tenant, $store, $payment): Payment {
            Order::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($payment->order_id);
            $locked = Payment::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($payment->getKey());
            if ($locked->status === PaymentStatus::Cancelled) {
                return $locked;
            }
            if ($locked->status !== PaymentStatus::Pending || $locked->transactions()->exists()) {
                throw ValidationException::withMessages(['payment' => 'Only an uncaptured pending payment can be cancelled.']);
            }
            $locked->update(['status' => PaymentStatus::Cancelled]);

            return $locked;
        });
    }
}
