<?php

namespace Tests\Feature\Orders;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Customers\Application\CreateCustomer;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Payments\PaymentsFeatureTestCase;

class OrderAdminListAndNavigationTest extends PaymentsFeatureTestCase
{
    /** @param array<string, mixed> $attributes */
    private function customer(Tenant $tenant, array $attributes = []): Customer
    {
        return app(CreateCustomer::class)->handle($tenant, array_replace([
            'first_name' => 'Navi', 'last_name' => 'Gator',
            'email' => fake()->unique()->safeEmail(), 'phone' => null, 'notes' => null,
        ], $attributes));
    }

    public function test_index_filters_by_customer_and_date_range(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $customer = $this->customer($tenant);
        [$mine] = $this->readyOrder($tenant, $store, $user, 1);
        [$other] = $this->readyOrder($tenant, $store, $user, 1);
        $mine->update(['customer_id' => $customer->getKey()]);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.orders.index', ['customer_id' => $customer->getKey()]))->assertInertia(
            fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $mine->getKey()),
        );
        $this->get(route('admin.orders.index', ['from' => now()->addDay()->toDateString()]))->assertInertia(
            fn (Assert $page) => $page->has('orders.data', 0),
        );
        $this->get(route('admin.orders.index', ['to' => now()->subDay()->toDateString()]))->assertInertia(
            fn (Assert $page) => $page->has('orders.data', 0),
        );
        $this->assertNotSame($mine->getKey(), $other->getKey());
    }

    public function test_invalid_filters_are_rejected_with_validation_errors(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->readyOrder($tenant, $store, $user, 1);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.orders.index', ['status' => 'NOT_A_STATUS']))->assertSessionHasErrors('status');
        $this->get(route('admin.orders.index', ['payment_status' => 'NOT_A_STATUS']))->assertSessionHasErrors('payment_status');
        $this->get(route('admin.orders.index', ['customer_id' => 'not-a-uuid']))->assertSessionHasErrors('customer_id');
        $this->get(route('admin.orders.index', ['from' => '2026-05-02', 'to' => '2026-05-01']))->assertSessionHasErrors('to');
    }

    public function test_show_exposes_related_links_only_for_permitted_resources(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $customer = $this->customer($tenant);
        [$order] = $this->readyOrder($tenant, $store, $user, 5);
        $order->update(['customer_id' => $customer->getKey()]);
        $this->enablePayments($tenant, $store);
        $this->manualPayment($tenant, $store, $order, '10.0000', $user);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.orders.show', $order))->assertInertia(fn (Assert $page) => $page
            ->where('customer.id', $customer->getKey())
            ->where('relatedLinks.customer', url('/admin/customers/'.$customer->getKey()))
            ->where('relatedLinks.payments', url('/admin/payments').'?order_id='.$order->getKey())
            ->where('relatedLinks.refunds', null)
            ->where('relatedLinks.returns', null)
            ->where('relatedLinks.fulfillments', null));

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $memberStore = $this->createStore($memberTenant);
        $memberCustomer = $this->customer($memberTenant);
        [$memberOrder] = $this->readyOrder($memberTenant, $memberStore, $member, 5);
        $memberOrder->update(['customer_id' => $memberCustomer->getKey()]);
        $this->enablePayments($memberTenant, $memberStore);
        $this->manualPayment($memberTenant, $memberStore, $memberOrder, '10.0000', $member);
        $this->useContext($member, $memberTenant, $memberStore);

        $this->get(route('admin.orders.show', $memberOrder))->assertInertia(fn (Assert $page) => $page
            ->where('customer.id', $memberCustomer->getKey())
            ->where('relatedLinks.customer', url('/admin/customers/'.$memberCustomer->getKey()))
            ->where('relatedLinks.payments', null)
            ->where('paymentData', null));
    }
}
