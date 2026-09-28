<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Models\Refund;
use Inertia\Testing\AssertableInertia;

class RefundHttpAndIsolationTest extends RefundsFeatureTestCase
{
    public function test_owner_and_admin_can_view_create_and_complete_refunds(): void
    {
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $user = User::factory()->create();
            $tenant = $this->createTenantFor($user, $role);
            [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), $user, 5);
            $this->enablePayments($tenant, $store);
            [$payment] = $this->capturedPayment($tenant, $store, $order, '500.0000', $user, "capture-{$role->value}");
            $this->useContext($user, $tenant, $store);

            $this->get(route('admin.refunds.index'))->assertOk();
            $this->post(route('admin.payments.refunds.store', $payment), [
                'amount' => '100.0000',
                'reason' => 'HTTP refund',
                'idempotency_key' => "create-{$role->value}",
                'return_id' => null,
            ])->assertRedirect();
            $refund = Refund::query()->where('payment_id', $payment->getKey())->sole();
            $this->get(route('admin.refunds.show', $refund))->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('refund.id', $refund->getKey())
                    ->where('refund.return_id', null)
                    ->where('capabilities.complete', true),
            );
            $this->post(route('admin.refunds.complete', $refund), [
                'idempotency_key' => "complete-{$role->value}",
                'external_reference' => "HTTP-{$role->value}",
            ])->assertRedirect();
            $this->assertSame(RefundStatus::Completed, $refund->fresh()->status);
        }
    }

    public function test_member_has_no_refund_data_or_route_access(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant), quantity: 5);
        $this->enablePayments($tenant, $store);
        [$payment] = $this->capturedPayment($tenant, $store, $order, '500.0000');
        $refund = $this->createRefund($tenant, $store, $payment, '100.0000', 'member-hidden');
        $this->useContext($member, $tenant, $store);

        $this->get(route('admin.refunds.index'))->assertForbidden();
        $this->get(route('admin.refunds.show', $refund))->assertForbidden();
        $this->post(route('admin.payments.refunds.store', $payment), [
            'amount' => '1.0000', 'reason' => 'Forbidden', 'idempotency_key' => 'member-create',
        ])->assertForbidden();
        $this->post(route('admin.refunds.complete', $refund), [
            'idempotency_key' => 'member-complete', 'external_reference' => 'MEMBER',
        ])->assertForbidden();
        $this->get(route('admin.orders.show', $order))->assertInertia(
            fn (AssertableInertia $page) => $page->where('paymentData', null),
        );
    }

    public function test_refund_http_routes_return_404_for_other_store_and_tenant_resources(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        [$orderB] = $this->readyOrder($tenant, $storeB, quantity: 5);
        $this->enablePayments($tenant, $storeB);
        [$paymentB] = $this->capturedPayment($tenant, $storeB, $orderB, '500.0000');
        $refundB = $this->createRefund($tenant, $storeB, $paymentB, '100.0000', 'store-b');

        $foreignTenant = $this->createTenantFor(User::factory()->create());
        [$foreignOrder] = $this->readyOrder($foreignTenant, $foreignStore = $this->createStore($foreignTenant), quantity: 5);
        $this->enablePayments($foreignTenant, $foreignStore);
        [$foreignPayment] = $this->capturedPayment($foreignTenant, $foreignStore, $foreignOrder, '500.0000');
        $foreignRefund = $this->createRefund($foreignTenant, $foreignStore, $foreignPayment, '100.0000', 'foreign');
        $this->useContext($user, $tenant, $storeA);

        foreach ([[$paymentB, $refundB], [$foreignPayment, $foreignRefund]] as [$payment, $refund]) {
            $this->get(route('admin.refunds.show', $refund))->assertNotFound();
            $this->post(route('admin.payments.refunds.store', $payment), [
                'amount' => '1.0000', 'reason' => 'Isolation attempt', 'idempotency_key' => 'isolation-create',
            ])->assertNotFound();
            $this->post(route('admin.refunds.complete', $refund), [
                'idempotency_key' => 'isolation-complete', 'external_reference' => 'ISOLATION',
            ])->assertNotFound();
        }
    }
}
