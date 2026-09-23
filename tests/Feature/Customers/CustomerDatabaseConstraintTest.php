<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Customers\Application\CreateCustomerAddress;
use App\Modules\Customers\Domain\Models\CustomerAddress;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerDatabaseConstraintTest extends CustomersFeatureTestCase
{
    public function test_customer_identity_status_and_tenant_unique_constraints_are_enforced(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $this->createCustomer($tenant);

        foreach ([
            ['first_name' => null, 'last_name' => null, 'email' => null, 'phone' => null, 'status' => 'ACTIVE'],
            ['first_name' => 'Bad', 'last_name' => null, 'email' => null, 'phone' => null, 'status' => 'UNKNOWN'],
            ['first_name' => 'Duplicate', 'last_name' => null, 'email' => 'ada@example.test', 'phone' => '+12025550300', 'status' => 'ACTIVE'],
            ['first_name' => 'Duplicate', 'last_name' => null, 'email' => 'different@example.test', 'phone' => '+51999111222', 'status' => 'ACTIVE'],
        ] as $attributes) {
            try {
                DB::transaction(fn () => DB::table('customers')->insert([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenant->getKey(),
                    ...$attributes,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
                $this->fail('Expected the database to reject invalid customer data.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_address_composite_tenant_and_partial_default_constraints_are_enforced(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $customerA = $this->createCustomer($tenantA);
        $customerB = $this->createCustomer($tenantB, ['email' => 'b@example.test', 'phone' => '+12025550301']);
        app(CreateCustomerAddress::class)->handle($tenantA, $customerA, [...$this->addressPayload(), 'is_default_shipping' => true]);

        $invalidRows = [
            [...$this->addressPayload(), 'tenant_id' => $tenantA->getKey(), 'customer_id' => $customerB->getKey()],
            [...$this->addressPayload(), 'tenant_id' => $tenantA->getKey(), 'customer_id' => $customerA->getKey(), 'country_code' => 'per'],
            [...$this->addressPayload(), 'tenant_id' => $tenantA->getKey(), 'customer_id' => $customerA->getKey(), 'is_default_shipping' => true],
        ];
        foreach ($invalidRows as $attributes) {
            try {
                DB::transaction(fn () => CustomerAddress::create(['id' => (string) Str::uuid7(), ...$attributes]));
                $this->fail('Expected the database to reject invalid address data.');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }
    }
}
