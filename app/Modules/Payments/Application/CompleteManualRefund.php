<?php

namespace App\Modules\Payments\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompleteManualRefund
{
    public function __construct(
        private readonly OrderPaymentStatusUpdater $statusUpdater,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(
        Tenant $tenant,
        Store $store,
        Refund $refund,
        string $idempotencyKey,
        string $externalReference,
        ?User $actor = null,
    ): Refund {
        $key = trim($idempotencyKey);
        $reference = trim($externalReference);
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'An idempotency key is required.']);
        }
        if ($reference === '') {
            throw ValidationException::withMessages(['external_reference' => 'An external reference is required.']);
        }

        return DB::transaction(function () use ($tenant, $store, $refund, $key, $reference, $actor): Refund {
            $order = Order::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->lockForUpdate()->findOrFail($refund->order_id);
            $payment = Payment::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->where('order_id', $order->getKey())->lockForUpdate()->findOrFail($refund->payment_id);
            $lockedRefund = Refund::query()->where('tenant_id', $tenant->getKey())->where('store_id', $store->getKey())
                ->where('order_id', $order->getKey())->where('payment_id', $payment->getKey())
                ->lockForUpdate()->findOrFail($refund->getKey());
            $transactions = $payment->transactions()->where('tenant_id', $tenant->getKey())
                ->orderBy('id')->lockForUpdate()->get();
            $existing = $transactions->firstWhere('idempotency_key', $key);
            if ($existing !== null) {
                if (! $this->matches($existing, $lockedRefund, $reference)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was used with different refund completion data.']);
                }

                $this->resolvePendingAfterCommit($lockedRefund);

                return $lockedRefund->fresh();
            }
            $externalMatch = $transactions->firstWhere('external_id', $reference);
            if ($externalMatch !== null) {
                throw ValidationException::withMessages(['external_reference' => 'This external reference is already in use for the payment.']);
            }
            if ($lockedRefund->status !== RefundStatus::Pending) {
                throw ValidationException::withMessages(['refund' => 'Only pending refunds can be completed.']);
            }
            if ($lockedRefund->currency !== $payment->currency || $payment->currency !== $order->currency) {
                throw ValidationException::withMessages(['currency' => 'Refund, payment, and order currencies must match.']);
            }

            $captured = $this->sum($transactions, PaymentTransactionType::Capture);
            $refunded = $this->sum($transactions, PaymentTransactionType::Refund);
            if (BigDecimal::of($lockedRefund->amount)->isGreaterThan($captured->minus($refunded))) {
                throw ValidationException::withMessages(['amount' => 'The refund amount exceeds the refundable payment balance.']);
            }

            $transaction = $payment->transactions()->create([
                'tenant_id' => $tenant->getKey(), 'refund_id' => $lockedRefund->getKey(), 'type' => PaymentTransactionType::Refund,
                'status' => PaymentTransactionStatus::Succeeded, 'amount' => $lockedRefund->amount,
                'currency' => $lockedRefund->currency, 'external_id' => $reference,
                'idempotency_key' => $key, 'occurred_at' => now(),
                'metadata' => ['refund_id' => $lockedRefund->getKey(), 'reason' => $lockedRefund->reason],
                'actor_user_id' => $actor?->getKey(),
            ]);
            $lockedRefund->update([
                'status' => RefundStatus::Completed,
                'external_reference' => $reference, 'completed_by_user_id' => $actor?->getKey(),
                'completed_at' => now(),
            ]);
            $this->statusUpdater->update($order);
            $this->audit->record(
                $tenant,
                $actor === null ? AuditActorType::System : AuditActorType::User,
                AuditAction::RefundCompleted,
                AuditSubjectType::Refund,
                (string) $lockedRefund->getKey(),
                [
                    'payment_id' => $payment->getKey(),
                    'amount' => $lockedRefund->amount,
                    'currency' => $lockedRefund->currency,
                ],
                $store,
                $actor,
                idempotencyKey: $key,
                subjectLabel: $order->order_number,
            );
            $this->resolvePendingAfterCommit($lockedRefund);

            return $lockedRefund->fresh();
        });
    }

    private function resolvePendingAfterCommit(Refund $refund): void
    {
        DB::afterCommit(function () use ($refund): void {
            try {
                DB::table('admin_notifications')
                    ->where('tenant_id', $refund->tenant_id)
                    ->where('store_id', $refund->store_id)
                    ->where('deduplication_key', 'condition:refund:'.$refund->getKey())
                    ->whereNull('resolved_at')
                    ->whereNull('dismissed_at')
                    ->update(['resolved_at' => now(), 'updated_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    private function matches(PaymentTransaction $transaction, Refund $refund, string $reference): bool
    {
        return $transaction->type === PaymentTransactionType::Refund
            && $transaction->status === PaymentTransactionStatus::Succeeded
            && BigDecimal::of($transaction->amount)->isEqualTo(BigDecimal::of($refund->amount))
            && $transaction->currency === $refund->currency
            && $transaction->external_id === $reference
            && ($transaction->metadata['refund_id'] ?? null) === $refund->getKey()
            && ($transaction->metadata['reason'] ?? null) === $refund->reason;
    }

    private function sum($transactions, PaymentTransactionType $type): BigDecimal
    {
        return $transactions->where('status', PaymentTransactionStatus::Succeeded)->where('type', $type)
            ->reduce(fn (BigDecimal $total, PaymentTransaction $transaction): BigDecimal => $total->plus($transaction->amount), BigDecimal::zero())
            ->toScale(4);
    }
}
