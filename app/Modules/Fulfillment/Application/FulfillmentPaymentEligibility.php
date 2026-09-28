<?php

namespace App\Modules\Fulfillment\Application;

use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Application\OrderPaymentCalculator;
use App\Modules\Payments\Domain\Enums\PaymentTerms;
use App\Modules\Payments\Domain\Models\OrderPaymentPlan;
use Brick\Math\BigDecimal;

class FulfillmentPaymentEligibility
{
    public function __construct(private readonly OrderPaymentCalculator $payments) {}

    public function check(Order $order): bool
    {
        return $this->evaluate($order)['eligible'];
    }

    /** @return array{eligible: bool, reason: ?string} */
    public function evaluate(Order $order): array
    {
        if ($order->status !== OrderStatus::Confirmed) {
            return ['eligible' => false, 'reason' => 'ORDER_NOT_CONFIRMED'];
        }

        $plan = OrderPaymentPlan::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('order_id', $order->getKey())
            ->first();
        $required = $plan?->terms === PaymentTerms::Cod ? $plan->amount_due_now : $order->grand_total;

        $eligible = BigDecimal::of($this->payments->calculate($order)->netPaid)
            ->isGreaterThanOrEqualTo(BigDecimal::of((string) $required));

        return [
            'eligible' => $eligible,
            'reason' => $eligible ? null : ($plan?->terms === PaymentTerms::Cod
                ? 'COD_DEPOSIT_REQUIRED'
                : 'PREPAID_PAYMENT_REQUIRED'),
        ];
    }
}
