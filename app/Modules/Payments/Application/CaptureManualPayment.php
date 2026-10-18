<?php

namespace App\Modules\Payments\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentProvider;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaptureManualPayment
{
    public function __construct(
        private readonly OrderPaymentCalculator $calculator,
        private readonly OrderPaymentStatusUpdater $statusUpdater,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(
        Tenant $tenant,
        Store $store,
        Payment $payment,
        string $amount,
        string $idempotencyKey,
        ?string $externalReference = null,
        ?User $actor = null,
    ): PaymentTransaction {
        $key = trim($idempotencyKey);
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An idempotency key is required.']);
        }
        $normalized = BigDecimal::of($amount)->toScale(4);
        $referenceHash = $externalReference === null ? null : hash('sha256', $externalReference);

        return DB::transaction(function () use ($tenant, $store, $payment, $normalized, $key, $externalReference, $referenceHash, $actor): PaymentTransaction {
            $lockedOrder = Order::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($payment->order_id);
            $lockedPayment = Payment::query()->where('tenant_id', $tenant->getKey())
                ->where('store_id', $store->getKey())->where('order_id', $lockedOrder->getKey())
                ->lockForUpdate()->findOrFail($payment->getKey());
            $existing = $lockedPayment->transactions()->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if (! BigDecimal::of($existing->amount)->isEqualTo($normalized)
                    || $existing->currency !== $lockedPayment->currency
                    || ($existing->metadata['external_reference_hash'] ?? null) !== $referenceHash) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was used with different capture data.']);
                }

                return $existing;
            }
            if (in_array($lockedOrder->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
                throw ValidationException::withMessages(['payment' => 'This order does not accept captures.']);
            }
            if ($lockedPayment->provider !== PaymentProvider::Manual || $lockedPayment->status !== PaymentStatus::Pending) {
                throw ValidationException::withMessages(['payment' => 'Only pending manual payments can be captured.']);
            }
            if (! $normalized->isEqualTo($lockedPayment->amount)) {
                throw ValidationException::withMessages(['amount' => 'Manual captures must equal the payment amount.']);
            }
            if ($lockedPayment->currency !== $lockedOrder->currency) {
                throw ValidationException::withMessages(['currency' => 'Payment currency must match the order currency.']);
            }
            $balance = $this->calculator->calculate($lockedOrder);
            if ($normalized->isGreaterThan($balance->remainingTotal)) {
                throw ValidationException::withMessages(['amount' => 'This capture would exceed the order total.']);
            }

            $transaction = $lockedPayment->transactions()->create([
                'tenant_id' => $tenant->getKey(), 'type' => PaymentTransactionType::Capture,
                'status' => PaymentTransactionStatus::Succeeded, 'amount' => (string) $normalized,
                'currency' => $lockedOrder->currency, 'idempotency_key' => $key, 'occurred_at' => now(),
                'metadata' => ['external_reference_hash' => $referenceHash],
                'actor_user_id' => $actor?->getKey(),
            ]);
            $lockedPayment->update([
                'status' => PaymentStatus::Captured,
                'external_reference' => $externalReference ?: $lockedPayment->external_reference,
            ]);
            $this->statusUpdater->update($lockedOrder);
            $this->audit->record(
                $tenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::PaymentCaptured,
                AuditSubjectType::Payment,
                (string) $lockedPayment->getKey(),
                [
                    'transaction_id' => $transaction->getKey(),
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                ],
                $store,
                $actor,
                idempotencyKey: $key,
                subjectLabel: $lockedOrder->order_number,
            );

            return $transaction;
        });
    }
}
