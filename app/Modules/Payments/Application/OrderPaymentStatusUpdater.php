<?php

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Domain\Models\Order;

class OrderPaymentStatusUpdater
{
    public function __construct(private readonly OrderPaymentCalculator $calculator) {}

    public function update(Order $lockedOrder): OrderPaymentBalance
    {
        $balance = $this->calculator->calculate($lockedOrder);
        if ($lockedOrder->payment_status !== $balance->paymentStatus) {
            $lockedOrder->update(['payment_status' => $balance->paymentStatus]);
        }

        return $balance;
    }
}
