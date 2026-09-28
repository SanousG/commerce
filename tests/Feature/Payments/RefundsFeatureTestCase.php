<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Application\CaptureManualPayment;
use App\Modules\Payments\Application\CompleteManualRefund;
use App\Modules\Payments\Application\CreateRefund;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Tests\Feature\Fulfillment\FulfillmentFeatureTestCase;

abstract class RefundsFeatureTestCase extends FulfillmentFeatureTestCase
{
    /** @return array{Payment, PaymentTransaction} */
    protected function capturedPayment(
        Tenant $tenant,
        Store $store,
        Order $order,
        string $amount,
        ?User $actor = null,
        string $key = 'capture-refund-fixture',
    ): array {
        $payment = $this->manualPayment($tenant, $store, $order, $amount, $actor);
        $capture = app(CaptureManualPayment::class)->handle(
            $tenant,
            $store,
            $payment,
            $amount,
            $key,
            actor: $actor,
        );

        return [$payment->fresh(), $capture];
    }

    protected function createRefund(
        Tenant $tenant,
        Store $store,
        Payment $payment,
        string $amount,
        string $key,
        string $reason = 'Customer requested refund',
        ?User $actor = null,
    ): Refund {
        return app(CreateRefund::class)->handle(
            $tenant,
            $store,
            $payment,
            $amount,
            $reason,
            $key,
            actor: $actor,
        );
    }

    protected function completeRefund(
        Tenant $tenant,
        Store $store,
        Refund $refund,
        string $key,
        string $reference,
        ?User $actor = null,
    ): Refund {
        return app(CompleteManualRefund::class)->handle(
            $tenant,
            $store,
            $refund,
            $key,
            $reference,
            $actor,
        );
    }
}
