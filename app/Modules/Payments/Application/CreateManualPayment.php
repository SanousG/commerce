<?php

namespace App\Modules\Payments\Application;

use App\Models\User;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentProvider;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\StorePaymentSettings;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateManualPayment
{
    public function __construct(private readonly OrderPaymentCalculator $calculator) {}

    public function handle(
        Tenant $tenant,
        Store $store,
        Order $order,
        PaymentMethod $method,
        string $amount,
        string $idempotencyKey,
        ?string $externalReference = null,
        ?User $actor = null,
    ): Payment {
        $key = trim($idempotencyKey);
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An idempotency key is required.']);
        }

        return DB::transaction(function () use ($tenant, $store, $order, $method, $amount, $key, $externalReference, $actor): Payment {
            $locked = Order::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($order->getKey());
            if (in_array($locked->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
                throw ValidationException::withMessages(['payment' => 'This order does not accept new payments.']);
            }
            $existing = Payment::query()->where('order_id', $locked->getKey())->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                $sameRequest = $existing->method === $method
                    && BigDecimal::of($existing->amount)->isEqualTo(BigDecimal::of($amount))
                    && $existing->external_reference === $externalReference;
                if (! $sameRequest) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was used with different payment data.']);
                }

                return $existing;
            }
            if (! in_array($method, [PaymentMethod::BankTransfer, PaymentMethod::Cash], true)) {
                throw ValidationException::withMessages(['method' => 'Only manual payment methods are available.']);
            }
            $settings = StorePaymentSettings::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->first();
            $enabled = $method === PaymentMethod::BankTransfer
                ? $settings?->bank_transfer_enabled
                : $settings?->cash_enabled;
            if (! $enabled) {
                throw ValidationException::withMessages(['method' => 'This payment method is not enabled for the store.']);
            }

            $normalized = BigDecimal::of($amount)->toScale(4);
            $balance = $this->calculator->calculate($locked);
            if ($normalized->isLessThanOrEqualTo(0) || $normalized->isGreaterThan($balance->remainingTotal)) {
                throw ValidationException::withMessages(['amount' => 'The payment amount must be positive and not exceed the remaining balance.']);
            }

            return Payment::query()->create([
                'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'order_id' => $locked->getKey(),
                'method' => $method, 'provider' => PaymentProvider::Manual, 'status' => PaymentStatus::Pending,
                'currency' => $locked->currency, 'amount' => (string) $normalized,
                'idempotency_key' => $key,
                'external_reference' => $externalReference, 'created_by_user_id' => $actor?->getKey(),
            ]);
        });
    }
}
