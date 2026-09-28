<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Fulfillment\Application\FulfillmentPaymentEligibility;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Payments\Application\CompleteManualRefund;
use App\Modules\Payments\Application\CreateRefund;
use App\Modules\Payments\Application\OrderPaymentCalculator;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use App\Modules\Payments\Domain\Models\Refund;
use Illuminate\Validation\ValidationException;

class RefundLifecycleTest extends RefundsFeatureTestCase
{
    public function test_capture_500_can_be_refunded_by_100_then_400_with_immutable_successful_ledger_and_correct_balances(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $actor, 5);
        $this->enablePayments($tenant, $store);
        [$payment, $capture] = $this->capturedPayment($tenant, $store, $order, '500.0000', $actor);
        $captureSnapshot = $capture->only(['id', 'payment_id', 'type', 'status', 'amount', 'currency', 'idempotency_key']);

        $first = $this->createRefund($tenant, $store, $payment, '100.0000', 'refund-create-100', actor: $actor);
        $this->assertNull($first->return_id);
        $first = $this->completeRefund($tenant, $store, $first, 'refund-complete-100', 'MANUAL-100', $actor);

        $this->assertSame(RefundStatus::Completed, $first->status);
        $firstBalance = app(OrderPaymentCalculator::class)->calculate($order->fresh());
        $this->assertSame('500.0000', $firstBalance->capturedTotal);
        $this->assertSame('100.0000', $firstBalance->refundedTotal);
        $this->assertSame('400.0000', $firstBalance->netPaid);
        $this->assertSame('100.0000', $firstBalance->remainingDue);
        $this->assertSame(OrderPaymentStatus::PartiallyRefunded, $firstBalance->paymentStatus);
        $this->assertSame(OrderPaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);

        $second = $this->createRefund($tenant, $store, $payment, '400.0000', 'refund-create-400', actor: $actor);
        $this->completeRefund($tenant, $store, $second, 'refund-complete-400', 'MANUAL-400', $actor);
        $balance = app(OrderPaymentCalculator::class)->calculate($order->fresh());

        $this->assertSame('500.0000', $balance->capturedTotal);
        $this->assertSame('500.0000', $balance->refundedTotal);
        $this->assertSame('0.0000', $balance->netPaid);
        $this->assertSame('500.0000', $balance->remainingDue);
        $this->assertSame(OrderPaymentStatus::Refunded, $balance->paymentStatus);
        $this->assertSame(OrderPaymentStatus::Refunded, $order->fresh()->payment_status);
        $this->assertFalse(app(FulfillmentPaymentEligibility::class)->check($order->fresh()));
        $this->assertSame($captureSnapshot, $capture->fresh()->only(array_keys($captureSnapshot)));
        $this->assertSame(1, PaymentTransaction::query()->where('type', PaymentTransactionType::Capture->value)->count());
        $this->assertSame(2, PaymentTransaction::query()
            ->where('type', PaymentTransactionType::Refund->value)
            ->where('status', PaymentTransactionStatus::Succeeded->value)->count());
    }

    public function test_multiple_pending_and_completed_refunds_cannot_overflow_the_capture(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 5);
        $this->enablePayments($tenant, $store);
        [$payment] = $this->capturedPayment($tenant, $store, $order, '500.0000');
        $first = $this->createRefund($tenant, $store, $payment, '300.0000', 'pending-300');

        try {
            $this->createRefund($tenant, $store, $payment, '200.0001', 'overflow-pending');
            $this->fail('Pending refunds did not reserve the refundable balance.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->completeRefund($tenant, $store, $first, 'complete-300', 'REF-300');
        $second = $this->createRefund($tenant, $store, $payment, '200.0000', 'pending-200');
        $this->completeRefund($tenant, $store, $second, 'complete-200', 'REF-200');

        $this->assertSame(2, Refund::query()->where('status', RefundStatus::Completed->value)->count());
        $this->expectException(ValidationException::class);
        $this->createRefund($tenant, $store, $payment, '0.0001', 'overflow-completed');
    }

    public function test_refund_creation_and_completion_are_idempotent_and_reject_conflicting_payloads(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 5);
        $this->enablePayments($tenant, $store);
        [$payment] = $this->capturedPayment($tenant, $store, $order, '500.0000');
        $create = app(CreateRefund::class);
        $first = $create->handle($tenant, $store, $payment, '100.0000', 'Duplicate-safe', 'create-idem');
        $same = $create->handle($tenant, $store, $payment, '100', 'Duplicate-safe', 'create-idem');

        $this->assertSame($first->getKey(), $same->getKey());
        $this->assertSame(1, Refund::query()->count());
        try {
            $create->handle($tenant, $store, $payment, '100.0001', 'Duplicate-safe', 'create-idem');
            $this->fail('Conflicting refund creation reused an idempotency key.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }

        $complete = app(CompleteManualRefund::class);
        $completed = $complete->handle($tenant, $store, $first, 'complete-idem', 'EXT-IDEM');
        $again = $complete->handle($tenant, $store, $first->fresh(), 'complete-idem', 'EXT-IDEM');
        $this->assertSame($completed->getKey(), $again->getKey());
        $this->assertSame(1, PaymentTransaction::query()->where('type', PaymentTransactionType::Refund->value)->count());

        $this->expectException(ValidationException::class);
        $complete->handle($tenant, $store, $first->fresh(), 'complete-idem', 'EXT-CONFLICT');
    }

    public function test_big_decimal_refunds_preserve_exact_four_decimal_arithmetic(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 5);
        $this->enablePayments($tenant, $store);
        [$payment] = $this->capturedPayment($tenant, $store, $order, '500.0000');
        $tiny = $this->createRefund($tenant, $store, $payment, '0.0001', 'decimal-small');
        $this->completeRefund($tenant, $store, $tiny, 'decimal-small-complete', 'DECIMAL-1');
        $rest = $this->createRefund($tenant, $store, $payment, '499.9999', 'decimal-rest');
        $this->completeRefund($tenant, $store, $rest, 'decimal-rest-complete', 'DECIMAL-2');

        $balance = app(OrderPaymentCalculator::class)->calculate($order->fresh());
        $this->assertSame('500.0000', $balance->refundedTotal);
        $this->assertSame('0.0000', $balance->netPaid);
        $this->assertSame(['0.0001', '499.9999'], Refund::query()->orderBy('amount')->pluck('amount')->all());
    }

    public function test_refund_requires_no_return_and_does_not_change_inventory_or_fulfillment(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $actor, 5);
        $this->enablePayments($tenant, $store);
        [$payment] = $this->capturedPayment($tenant, $store, $order, '500.0000', $actor);
        $fulfillment = $this->complete($tenant, $this->fulfillment($tenant, $order, 2, actor: $actor), $actor);
        $inventory = [$level->fresh()->on_hand, $level->fresh()->reserved, $level->fresh()->available];
        $fulfillmentStatus = $fulfillment->fresh()->status;

        $refund = $this->createRefund($tenant, $store, $payment, '100.0000', 'without-return', actor: $actor);
        $this->completeRefund($tenant, $store, $refund, 'without-return-complete', 'NO-RETURN', $actor);

        $this->assertNull($refund->return_id);
        $this->assertSame($inventory, [$level->fresh()->on_hand, $level->fresh()->reserved, $level->fresh()->available]);
        $this->assertSame($fulfillmentStatus, $fulfillment->fresh()->status);
        $this->assertSame(OrderFulfillmentStatus::PartiallyFulfilled, $order->fresh()->fulfillment_status);
    }
}
