<?php

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use Brick\Math\BigDecimal;

class OrderPaymentCalculator
{
    public function calculate(Order $order): OrderPaymentBalance
    {
        $transactions = PaymentTransaction::query()
            ->join('payments', 'payments.id', '=', 'payment_transactions.payment_id')
            ->where('payment_transactions.tenant_id', $order->tenant_id)
            ->where('payments.tenant_id', $order->tenant_id)
            ->where('payments.store_id', $order->store_id)
            ->where('payments.order_id', $order->getKey())
            ->where('payment_transactions.status', PaymentTransactionStatus::Succeeded->value)
            ->whereIn('payment_transactions.type', [PaymentTransactionType::Capture->value, PaymentTransactionType::Refund->value])
            ->get(['payment_transactions.type', 'payment_transactions.amount']);

        $total = BigDecimal::of($order->grand_total)->toScale(4);
        $sum = fn (PaymentTransactionType $type): BigDecimal => $transactions->where('type', $type)
            ->reduce(fn (BigDecimal $amount, PaymentTransaction $transaction): BigDecimal => $amount->plus($transaction->amount), BigDecimal::zero())
            ->toScale(4);
        $capturedTotal = $sum(PaymentTransactionType::Capture);
        $refundedTotal = $sum(PaymentTransactionType::Refund);
        $netPaid = $capturedTotal->minus($refundedTotal);
        $remaining = $total->minus($netPaid);
        $status = match (true) {
            ! $refundedTotal->isZero() && $netPaid->isZero() => OrderPaymentStatus::Refunded,
            ! $refundedTotal->isZero() => OrderPaymentStatus::PartiallyRefunded,
            $netPaid->isZero() => OrderPaymentStatus::Unpaid,
            $netPaid->isLessThan($total) => OrderPaymentStatus::PartiallyPaid,
            default => OrderPaymentStatus::Paid,
        };

        return new OrderPaymentBalance(
            (string) $total,
            (string) $capturedTotal,
            (string) $refundedTotal,
            (string) $netPaid,
            (string) ($remaining->isNegative() ? BigDecimal::zero()->toScale(4) : $remaining),
            $status,
        );
    }
}
