<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Customers\Application\CreateCustomerAddress;
use App\Modules\Customers\Application\UpdateCustomerAddress;
use App\Modules\Customers\Domain\Models\CustomerAddress;
use App\Modules\Tenancy\Application\TenantContext;

class CustomerAddressTest extends CustomersFeatureTestCase
{
    public function test_customer_can_have_multiple_addresses_and_one_address_can_be_both_defaults(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $customer = $this->createCustomer($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->post(route('admin.customers.addresses.store', $customer), [
            ...$this->addressPayload(), 'is_default_shipping' => true, 'is_default_billing' => true,
        ])->assertRedirect();
        $this->post(route('admin.customers.addresses.store', $customer), [
            ...$this->addressPayload(), 'label' => 'Office', 'address_line_1' => 'Calle Office 456',
        ])->assertRedirect();

        $this->assertCount(2, $customer->addresses);
        $default = $customer->addresses()->where('label', 'Home')->sole();
        $this->assertTrue($default->is_default_shipping);
        $this->assertTrue($default->is_default_billing);
        $this->assertTrue($customer->addresses->every(fn (CustomerAddress $address) => $address->tenant_id === $tenant->getKey()));
    }

    public function test_creating_and_changing_defaults_is_transactional_and_unique(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $customer = $this->createCustomer($tenant);
        $first = app(CreateCustomerAddress::class)->handle($tenant, $customer, [
            ...$this->addressPayload(), 'is_default_shipping' => true, 'is_default_billing' => true,
        ]);
        $second = app(CreateCustomerAddress::class)->handle($tenant, $customer, [
            ...$this->addressPayload(), 'label' => 'Office', 'address_line_1' => 'Office 456',
        ]);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->post(route('admin.customers.addresses.default', [$customer, $second, 'shipping']))->assertRedirect();
        $this->assertFalse($first->fresh()->is_default_shipping);
        $this->assertTrue($second->fresh()->is_default_shipping);
        $this->assertTrue($first->fresh()->is_default_billing);
        $this->post(route('admin.customers.addresses.default', [$customer, $second, 'billing']))->assertRedirect();
        $this->assertFalse($first->fresh()->is_default_billing);
        $this->assertTrue($second->fresh()->is_default_billing);
    }

    public function test_address_routes_scope_customer_address_and_tenant_and_validate_country(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $customerA = $this->createCustomer($tenant);
        $customerB = $this->createCustomer($tenant, ['email' => 'b@example.test', 'phone' => '+12025550200']);
        $addressB = app(CreateCustomerAddress::class)->handle($tenant, $customerB, $this->addressPayload());
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $foreignCustomer = $this->createCustomer($foreignTenant, ['email' => 'foreign@example.test', 'phone' => '+12025550201']);
        $foreignAddress = app(CreateCustomerAddress::class)->handle($foreignTenant, $foreignCustomer, $this->addressPayload());
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->put(route('admin.customers.addresses.update', [$customerA, $addressB]), $this->addressPayload())->assertNotFound();
        $this->put(route('admin.customers.addresses.update', [$customerA, $foreignAddress]), $this->addressPayload())->assertNotFound();
        $this->post(route('admin.customers.addresses.store', $foreignCustomer), $this->addressPayload())->assertNotFound();
        $this->post(route('admin.customers.addresses.store', $customerA), [...$this->addressPayload(), 'country_code' => 'PER'])
            ->assertSessionHasErrors('country_code');
    }

    public function test_address_can_be_updated_and_deleted_without_deleting_customer(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $customer = $this->createCustomer($tenant);
        $address = app(CreateCustomerAddress::class)->handle($tenant, $customer, $this->addressPayload());
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->put(route('admin.customers.addresses.update', [$customer, $address]), [
            ...$this->addressPayload(), 'city' => 'Cusco', 'phone' => '+51 (999) 222-333',
        ])->assertRedirect();
        $this->assertSame('Cusco', $address->fresh()->city);
        $this->assertSame('+51999222333', $address->fresh()->phone);
        $this->delete(route('admin.customers.addresses.destroy', [$customer, $address]))->assertRedirect();
        $this->assertModelMissing($address);
        $this->assertModelExists($customer);
    }

    public function test_application_update_cannot_change_address_ownership(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $customer = $this->createCustomer($tenant);
        $foreignCustomer = $this->createCustomer($foreignTenant, ['email' => 'foreign@example.test', 'phone' => '+12025550999']);
        $address = app(CreateCustomerAddress::class)->handle($tenant, $customer, $this->addressPayload());

        app(UpdateCustomerAddress::class)->handle($tenant, $customer, $address, [
            ...$this->addressPayload(),
            'tenant_id' => $foreignTenant->getKey(),
            'customer_id' => $foreignCustomer->getKey(),
            'city' => 'Arequipa',
        ]);

        $this->assertSame($tenant->getKey(), $address->fresh()->tenant_id);
        $this->assertSame($customer->getKey(), $address->fresh()->customer_id);
        $this->assertSame('Arequipa', $address->fresh()->city);
    }
}
