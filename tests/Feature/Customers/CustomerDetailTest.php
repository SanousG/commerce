<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Customers\Application\CreateCustomer;
use App\Modules\Customers\Application\CreateCustomerAddress;
use App\Modules\Customers\Domain\Enums\CustomerStatus;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\OrdersFeatureTestCase;

class CustomerDetailTest extends OrdersFeatureTestCase
{
    /** @param array<string, mixed> $attributes */
    private function customer(Tenant $tenant, array $attributes = []): Customer
    {
        return app(CreateCustomer::class)->handle($tenant, array_replace([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'notes' => null,
        ], $attributes));
    }

    public function test_show_displays_customer_addresses_and_edit_capabilities(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $customer = $this->customer($tenant);
        app(CreateCustomerAddress::class)->handle($tenant, $customer, [
            'label' => 'Home', 'recipient_name' => 'Ada Lovelace', 'phone' => null,
            'country_code' => 'PE', 'region' => 'Lima', 'city' => 'Lima', 'district' => null,
            'address_line_1' => 'Av. Example 123', 'address_line_2' => null, 'postal_code' => null,
            'reference' => null, 'is_default_shipping' => true, 'is_default_billing' => false,
        ]);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.customers.show', $customer))->assertInertia(fn (Assert $page) => $page
            ->component('admin/customers/Show')
            ->where('customer.id', $customer->getKey())
            ->where('customer.display_name', 'Ada Lovelace')
            ->has('addresses', 1)
            ->where('addresses.0.label', 'Home')
            ->where('capabilities.update', true)
            ->where('capabilities.archive', true)
            ->where('capabilities.view_orders', true));
    }

    public function test_member_can_view_detail_but_cannot_edit_or_archive(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        $store = $this->createStore($tenant);
        $customer = $this->customer($tenant);
        $this->useContext($member, $tenant, $store);

        $this->get(route('admin.customers.show', $customer))->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->where('capabilities.update', false)
                ->where('capabilities.archive', false)
                ->where('capabilities.view_orders', true),
        );
    }

    public function test_show_returns_404_for_foreign_tenant_and_malformed_identifiers(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $foreign = $this->customer($this->createTenantFor(User::factory()->create()));
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.customers.show', $foreign))->assertNotFound();
        $this->get('/admin/customers/not-a-uuid')->assertNotFound();
    }

    public function test_show_order_history_is_tenant_wide_and_flags_other_store_orders(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $customer = $this->customer($tenant);
        [$orderA] = $this->readyOrder($tenant, $storeA, $user, 1);
        [$orderB] = $this->readyOrder($tenant, $storeB, $user, 1);
        $orderA->update(['customer_id' => $customer->getKey()]);
        $orderB->update(['customer_id' => $customer->getKey()]);
        $this->useContext($user, $tenant, $storeA);

        $this->get(route('admin.customers.show', $customer))->assertInertia(fn (Assert $page) => $page
            ->where('orderHistory.total', 2)
            ->has('orderHistory.data', 2)
            ->where('ordersInOtherStores', 1)
            ->where('currentStore.id', $storeA->getKey())
            ->where('orderHistory.data', function ($rows) use ($orderA, $orderB): bool {
                $byId = collect($rows)->keyBy('id');

                return $byId[$orderA->getKey()]['openable'] === true
                    && $byId[$orderB->getKey()]['openable'] === false;
            }));
    }

    public function test_index_filters_by_status_and_rejects_unknown_values(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $active = $this->customer($tenant, ['first_name' => 'ActiveOne', 'last_name' => '']);
        $blocked = $this->customer($tenant, ['first_name' => 'BlockedOne', 'last_name' => '']);
        $blocked->update(['status' => CustomerStatus::Blocked]);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.customers.index', ['status' => CustomerStatus::Blocked->value]))->assertInertia(
            fn (Assert $page) => $page->has('customers.data', 1)
                ->where('customers.data.0.id', $blocked->getKey()),
        );
        $this->get(route('admin.customers.index', ['status' => 'NOT_A_STATUS']))->assertSessionHasErrors('status');
        $this->assertNotSame($active->getKey(), $blocked->getKey());
    }
}
