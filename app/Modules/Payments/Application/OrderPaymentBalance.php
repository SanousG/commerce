<?php

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;

final readonly class OrderPaymentBalance
{
    public string $remainingTotal;

    public function __construct(
        public string $orderTotal,
        public string $capturedTotal,
        public string $refundedTotal,
        public string $netPaid,
        public string $remainingDue,
        public OrderPaymentStatus $paymentStatus,
    ) {
        $this->remainingTotal = $remainingDue;
    }

    public function toArray(): array
    {
        return [
            'order_total' => $this->orderTotal,
            'captured_total' => $this->capturedTotal,
            'refunded_total' => $this->refundedTotal,
            'net_paid' => $this->netPaid,
            'remaining_due' => $this->remainingDue,
            'remaining_total' => $this->remainingTotal,
            'payment_status' => $this->paymentStatus->value,
        ];
    }
}
