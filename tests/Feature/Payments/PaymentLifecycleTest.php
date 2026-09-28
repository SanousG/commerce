<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Orders\Application\CancelOrder;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Payments\Application\CancelPayment;
use App\Modules\Payments\Application\CaptureManualPayment;
use App\Modules\Payments\Application\CreateManualPayment;
use App\Modules\Payments\Application\OrderPaymentCalculator;
use App\Modules\Payments\Domain\Enums\PaymentMethod;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Models\PaymentTransaction;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

class PaymentLifecycleTest extends PaymentsFeatureTestCase
{
    public function test_multiple_manual_payments_progress_from_unpaid_to_partial_to_paid_without_inventory_changes(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        [$order, , , , $level] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $user, 2);
        $this->enablePayments($tenant, $store);
        $total = BigDecimal::of($order->grand_total)->toScale(4);
        $firstAmount = '10.0000';
        $secondAmount = (string) $total->minus($firstAmount);
        $onHand = $level->fresh()->on_hand;
        $reserved = $level->fresh()->reserved;

        $first = $this->manualPayment($tenant, $store, $order, $firstAmount, $user);
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->fresh()->payment_status);
        app(CaptureManualPayment::class)->handle($tenant, $store, $first, $firstAmount, 'capture-first', actor: $user);
        $this->assertSame(OrderPaymentStatus::PartiallyPaid, $order->fresh()->payment_status);
        $second = $this->manualPayment($tenant, $store, $order, $secondAmount, $user, PaymentMethod::Cash);
        app(CaptureManualPayment::class)->handle($tenant, $store, $second, $secondAmount, 'capture-second', actor: $user);

        $this->assertSame(OrderPaymentStatus::Paid, $order->fresh()->payment_status);
        $balance = app(OrderPaymentCalculator::class)->calculate($order->fresh());
        $this->assertSame((string) $total, $balance->capturedTotal);
        $this->assertSame('0.0000', $balance->remainingTotal);
        $this->assertSame($onHand, $level->fresh()->on_hand);
        $this->assertSame($reserved, $level->fresh()->reserved);
    }

    public function test_manual_capture_is_idempotent_and_competing_pending_payment_cannot_overpay(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->enablePayments($tenant, $store);
        $first = $this->manualPayment($tenant, $store, $order, $order->grand_total);
        $competing = $this->manualPayment($tenant, $store, $order, $order->grand_total);
        $action = app(CaptureManualPayment::class);
        $transaction = $action->handle($tenant, $store, $first, $order->grand_total, 'same-key');
        $duplicate = $action->handle($tenant, $store, $first->fresh(), $order->grand_total, 'same-key');

        $this->assertSame($transaction->getKey(), $duplicate->getKey());
        $this->assertSame(1, PaymentTransaction::query()->count());
        try {
            $action->handle($tenant, $store, $competing, $order->grand_total, 'competing-key');
            $this->fail('A competing capture exceeded the order total.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
            $this->assertSame(1, PaymentTransaction::query()->count());
        }
    }

    public function test_manual_payment_creation_is_idempotent_and_rejects_conflicting_reuse(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->enablePayments($tenant, $store);
        $action = app(CreateManualPayment::class);
        $first = $action->handle($tenant, $store, $order, PaymentMethod::Cash, '5.0000', 'create-key');
        $same = $action->handle($tenant, $store, $order, PaymentMethod::Cash, '5.0000', 'create-key');
        $this->assertSame($first->getKey(), $same->getKey());

        $this->expectException(ValidationException::class);
        $action->handle($tenant, $store, $order, PaymentMethod::Cash, '6.0000', 'create-key');
    }

    public function test_capture_idempotency_key_rejects_different_payload(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->enablePayments($tenant, $store);
        $payment = $this->manualPayment($tenant, $store, $order, '5.0000');
        $action = app(CaptureManualPayment::class);
        $action->handle($tenant, $store, $payment, '5.0000', 'capture-key', 'REF-1');

        $this->expectException(ValidationException::class);
        $action->handle($tenant, $store, $payment->fresh(), '5.0000', 'capture-key', 'REF-2');
    }

    public function test_pending_payment_can_cancel_but_captured_payment_and_paid_order_cannot_cancel(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->enablePayments($tenant, $store);
        $pending = $this->manualPayment($tenant, $store, $order, '5.0000');
        $cancelled = app(CancelPayment::class)->handle($tenant, $store, $pending);
        $this->assertSame(PaymentStatus::Cancelled, $cancelled->status);
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->fresh()->payment_status);

        $paid = $this->manualPayment($tenant, $store, $order, '5.0000');
        app(CaptureManualPayment::class)->handle($tenant, $store, $paid, '5.0000', 'paid-capture');
        foreach ([
            fn () => app(CancelPayment::class)->handle($tenant, $store, $paid->fresh()),
            fn () => app(CancelOrder::class)->handle($tenant, $order, 'Should require refund.'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Captured financial state was cancelled without a refund.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_cancelled_and_completed_orders_reject_new_payments(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $this->enablePayments($tenant, $store);
        [$cancelled] = $this->readyOrder($tenant, $store);
        app(CancelOrder::class)->handle($tenant, $cancelled, 'No longer needed.');
        [$completed] = $this->readyOrder($tenant, $store);
        $completed->update(['status' => OrderStatus::Completed]);

        foreach ([$cancelled->fresh(), $completed->fresh()] as $terminal) {
            try {
                $this->manualPayment($tenant, $store, $terminal, '1.0000');
                $this->fail("{$terminal->status->value} order accepted a payment.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment', $exception->errors());
            }
        }
    }
}
