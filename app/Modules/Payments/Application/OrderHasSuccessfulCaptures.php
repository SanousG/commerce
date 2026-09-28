<?php

namespace App\Modules\Payments\Application;

use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Models\PaymentTransaction;

class OrderHasSuccessfulCaptures
{
    public function check(Order $lockedOrder): bool
    {
        return PaymentTransaction::query()->join('payments', 'payments.id', '=', 'payment_transactions.payment_id')
            ->where('payments.tenant_id', $lockedOrder->tenant_id)
            ->where('payments.store_id', $lockedOrder->store_id)
            ->where('payments.order_id', $lockedOrder->getKey())
            ->where('payment_transactions.type', PaymentTransactionType::Capture->value)
            ->where('payment_transactions.status', PaymentTransactionStatus::Succeeded->value)
            ->exists();
    }
}
