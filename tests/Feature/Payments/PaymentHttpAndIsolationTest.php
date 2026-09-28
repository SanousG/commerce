<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use Inertia\Testing\AssertableInertia;

class PaymentHttpAndIsolationTest extends PaymentsFeatureTestCase
{
    public function test_owner_and_admin_have_payment_access_while_member_has_none(): void
    {
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $user = User::factory()->create();
            $tenant = $this->createTenantFor($user, $role);
            [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
            $this->enablePayments($tenant, $store);
            $payment = $this->manualPayment($tenant, $store, $order, '5.0000');
            $this->useContext($user, $tenant, $store);
            $this->get(route('admin.payments.index'))->assertOk();
            $this->get(route('admin.payments.show', $payment))->assertOk();
            $this->get(route('admin.payments.settings.show'))->assertOk();
        }

        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->useContext($member, $tenant, $store);
        $this->get(route('admin.payments.index'))->assertForbidden();
        $this->get(route('admin.payments.settings.show'))->assertForbidden();
        $this->post(route('admin.orders.payments.store', $order), ['method' => 'CASH', 'amount' => '1.0000'])
            ->assertForbidden();
        $this->get(route('admin.orders.show', $order))->assertInertia(
            fn (AssertableInertia $page) => $page->where('paymentData', null),
        );
    }

    public function test_payment_routes_are_store_and_tenant_isolated(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        [$orderA] = $this->readyOrder($tenant, $storeA);
        $this->enablePayments($tenant, $storeA);
        $paymentA = $this->manualPayment($tenant, $storeA, $orderA, '5.0000');
        $storeB = $this->createStore($tenant);
        [$orderB] = $this->readyOrder($tenant, $storeB);
        $this->enablePayments($tenant, $storeB);
        $paymentB = $this->manualPayment($tenant, $storeB, $orderB, '5.0000');
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        [$foreignOrder] = $this->readyOrder($foreignTenant, $foreignStore = $this->createStore($foreignTenant));
        $this->enablePayments($foreignTenant, $foreignStore);
        $foreignPayment = $this->manualPayment($foreignTenant, $foreignStore, $foreignOrder, '5.0000');
        $this->useContext($user, $tenant, $storeA);

        $this->get(route('admin.payments.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('payments.data', 1)->where('payments.data.0.id', $paymentA->getKey()));
        foreach ([$paymentB, $foreignPayment] as $inaccessible) {
            $this->get(route('admin.payments.show', $inaccessible))->assertNotFound();
            $this->post(route('admin.payments.capture', $inaccessible), [
                'amount' => '5.0000', 'idempotency_key' => 'foreign-attempt',
            ])->assertNotFound();
            $this->post(route('admin.payments.cancel', $inaccessible))->assertNotFound();
        }
    }

    public function test_settings_validation_and_order_payment_section(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        [$order] = $this->readyOrder($tenant, $store = $this->createStore($tenant));
        $this->useContext($user, $tenant, $store);
        $this->patch(route('admin.payments.settings.update'), [
            'bank_transfer_enabled' => true, 'cash_enabled' => true, 'online_enabled' => false,
            'cod_enabled' => true, 'cod_deposit_type' => 'PERCENTAGE', 'cod_deposit_value' => 'not-money',
        ])->assertSessionHasErrors('cod_deposit_value');
        $this->patch(route('admin.payments.settings.update'), [
            'bank_transfer_enabled' => true, 'cash_enabled' => true, 'online_enabled' => false,
            'cod_enabled' => true, 'cod_deposit_type' => 'PERCENTAGE', 'cod_deposit_value' => '101.0000',
        ])->assertSessionHasErrors('cod_deposit_value');
        $this->patch(route('admin.payments.settings.update'), [
            'bank_transfer_enabled' => true, 'cash_enabled' => true, 'online_enabled' => false,
            'cod_enabled' => true, 'cod_deposit_type' => 'PERCENTAGE', 'cod_deposit_value' => '20.0000',
        ])->assertRedirect();
        $this->get(route('admin.orders.show', $order))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('paymentData.balance.captured_total', '0.0000')
            ->where('paymentData.plan.terms', 'PREPAID')
            ->where('paymentData.enabled_methods.CASH', true));
    }
}
