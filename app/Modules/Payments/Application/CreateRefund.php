<?php

namespace App\Modules\Payments\Application;

use App\Models\User;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRefund
{
    public function handle(
        Tenant $tenant,
        Store $store,
        Payment $payment,
        string $amount,
        string $reason,
        string $idempotencyKey,
        ?ReturnRequest $return = null,
        ?User $actor = null,
    ): Refund {
        $key = trim($idempotencyKey);
        $normalizedAmount = BigDecimal::of($amount)->toScale(4);
        $normalizedReason = trim($reason);
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An idempotency key is required.']);
        }

        return DB::transaction(function () use ($tenant, $store, $payment, $normalizedAmount, $normalizedReason, $key, $return, $actor): Refund {
            $order = Order::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($payment->order_id);
            $lockedPayment = Payment::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('order_id', $order->getKey())
                ->lockForUpdate()->findOrFail($payment->getKey());
            $lockedReturn = $return === null ? null : ReturnRequest::query()
                ->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->where('order_id', $order->getKey())->lockForUpdate()->findOrFail($return->getKey());
            $existing = Refund::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('order_id', $order->getKey())
                ->where('payment_id', $lockedPayment->getKey())->where('idempotency_key', $key)
                ->lockForUpdate()->first();
            if ($existing !== null) {
                if (! BigDecimal::of($existing->amount)->isEqualTo($normalizedAmount)
                    || $existing->currency !== $lockedPayment->currency
                    || $existing->reason !== $normalizedReason
                    || $existing->return_id !== $lockedReturn?->getKey()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was used with different refund data.']);
                }

                return $existing;
            }
            if ($normalizedAmount->isLessThanOrEqualTo(0)) {
                throw ValidationException::withMessages(['amount' => 'The refund amount must be positive.']);
            }
            if ($normalizedReason === '') {
                throw ValidationException::withMessages(['reason' => 'A refund reason is required.']);
            }
            if ($lockedPayment->currency !== $order->currency) {
                throw ValidationException::withMessages(['currency' => 'Payment currency must match the order currency.']);
            }

            [$captured, $refunded] = $this->successfulTotals($lockedPayment);
            $pending = Refund::query()->where('tenant_id', $tenant->getKey())
                ->where('payment_id', $lockedPayment->getKey())->where('status', RefundStatus::Pending->value)
                ->sum('amount');
            if ($normalizedAmount->isGreaterThan($captured->minus($refunded)->minus((string) $pending))) {
                throw ValidationException::withMessages(['amount' => 'The refund amount exceeds the refundable payment balance.']);
            }

            return Refund::query()->create([
                'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'order_id' => $order->getKey(),
                'payment_id' => $lockedPayment->getKey(), 'status' => RefundStatus::Pending,
                'amount' => (string) $normalizedAmount, 'currency' => $lockedPayment->currency,
                'reason' => $normalizedReason, 'idempotency_key' => $key,
                'return_id' => $lockedReturn?->getKey(),
                'created_by_user_id' => $actor?->getKey(),
            ]);
        });
    }

    /** @return array{BigDecimal, BigDecimal} */
    private function successfulTotals(Payment $payment): array
    {
        $transactions = $payment->transactions()->where('tenant_id', $payment->tenant_id)
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->whereIn('type', [PaymentTransactionType::Capture->value, PaymentTransactionType::Refund->value])
            ->orderBy('id')->lockForUpdate()->get(['type', 'amount']);

        $sum = fn (PaymentTransactionType $type): BigDecimal => $transactions->where('type', $type)
            ->reduce(fn (BigDecimal $total, $transaction): BigDecimal => $total->plus($transaction->amount), BigDecimal::zero())
            ->toScale(4);

        return [$sum(PaymentTransactionType::Capture), $sum(PaymentTransactionType::Refund)];
    }
}
