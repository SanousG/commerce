<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use Inertia\Testing\AssertableInertia as Assert;

class PaymentAndRefundListFilterTest extends RefundsFeatureTestCase
{
    public function test_payments_index_filters_by_status_order_and_search(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        [$orderA] = $this->readyOrder($tenant, $store, $user, 5);
        [$orderB] = $this->readyOrder($tenant, $store, $user, 5);
        $this->enablePayments($tenant, $store);
        $paymentA = $this->manualPayment($tenant, $store, $orderA, '100.0000', $user);
        $paymentB = $this->manualPayment($tenant, $store, $orderB, '50.0000', $user);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.payments.index'))->assertInertia(
            fn (Assert $page) => $page->has('payments.data', 2),
        );
        $this->get(route('admin.payments.index', ['order_id' => $orderA->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('payments.data', 1)->where('payments.data.0.id', $paymentA->getKey()),
        );
        $this->get(route('admin.payments.index', ['search' => $orderB->order_number]))->assertInertia(
            fn (Assert $page) => $page->has('payments.data', 1)->where('payments.data.0.id', $paymentB->getKey()),
        );
        $this->get(route('admin.payments.index', ['status' => PaymentStatus::Pending->value]))->assertInertia(
            fn (Assert $page) => $page->has('payments.data', 2),
        );
        $this->get(route('admin.payments.index', ['status' => PaymentStatus::Captured->value]))->assertInertia(
            fn (Assert $page) => $page->has('payments.data', 0),
        );
        $this->get(route('admin.payments.index', ['status' => 'NOT_A_STATUS']))->assertSessionHasErrors('status');
    }

    public function test_payments_and_refunds_lists_hide_other_store_order_filters(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        [$orderA] = $this->readyOrder($tenant, $storeA, $user, 5);
        [$orderB] = $this->readyOrder($tenant, $storeB, $user, 5);
        $this->enablePayments($tenant, $storeA);
        $this->enablePayments($tenant, $storeB);
        [$paymentB] = $this->capturedPayment($tenant, $storeB, $orderB, '500.0000');
        $this->createRefund($tenant, $storeB, $paymentB, '100.0000', 'other-store-refund');
        $this->manualPayment($tenant, $storeA, $orderA, '100.0000', $user);
        $this->useContext($user, $tenant, $storeA);

        $this->get(route('admin.payments.index', ['order_id' => $orderB->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('payments.data', 0),
        );
        $this->get(route('admin.refunds.index', ['order_id' => $orderB->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('refunds.data', 0),
        );
    }

    public function test_refunds_index_filters_by_status_order_and_search(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        [$order] = $this->readyOrder($tenant, $store, $user, 5);
        $this->enablePayments($tenant, $store);
        [$payment] = $this->capturedPayment($tenant, $store, $order, '500.0000', $user);
        $refund = $this->createRefund($tenant, $store, $payment, '100.0000', 'filter-refund', actor: $user);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.refunds.index'))->assertInertia(
            fn (Assert $page) => $page->has('refunds.data', 1)->where('refunds.data.0.id', $refund->getKey()),
        );
        $this->get(route('admin.refunds.index', ['order_id' => $order->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('refunds.data', 1),
        );
        $this->get(route('admin.refunds.index', ['search' => $order->order_number]))->assertInertia(
            fn (Assert $page) => $page->has('refunds.data', 1),
        );
        $this->get(route('admin.refunds.index', ['status' => RefundStatus::Pending->value]))->assertInertia(
            fn (Assert $page) => $page->has('refunds.data', 1),
        );
        $this->get(route('admin.refunds.index', ['status' => RefundStatus::Completed->value]))->assertInertia(
            fn (Assert $page) => $page->has('refunds.data', 0),
        );
        $this->get(route('admin.refunds.index', ['status' => 'NOT_A_STATUS']))->assertSessionHasErrors('status');
    }

    public function test_member_without_payment_or_refund_access_cannot_read_lists(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        $store = $this->createStore($tenant);
        $this->readyOrder($tenant, $store, $member, 2);
        $this->useContext($member, $tenant, $store);

        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->get(route('admin.refunds.index'))->assertForbidden();
    }
}
