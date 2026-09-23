<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Customers\Application\CreateCustomer;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class CustomersFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createTenantFor(User $user, SystemRole $role = SystemRole::Owner): Tenant
    {
        $tenant = Tenant::create([
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
            'status' => TenantStatus::Active,
        ]);
        TenantMember::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->system($role)->sole()->getKey(),
            'status' => TenantMemberStatus::Active,
            'joined_at' => now(),
        ]);

        return $tenant;
    }

    /** @param array<string, mixed> $attributes */
    protected function createCustomer(Tenant $tenant, array $attributes = []): Customer
    {
        return app(CreateCustomer::class)->handle($tenant, array_replace($this->customerPayload(), $attributes));
    }

    /** @return array<string, mixed> */
    protected function customerPayload(): array
    {
        return [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+51999111222',
            'notes' => null,
        ];
    }

    /** @return array<string, mixed> */
    protected function addressPayload(): array
    {
        return [
            'label' => 'Home',
            'recipient_name' => 'Ada Lovelace',
            'phone' => '+51999111222',
            'country_code' => 'PE',
            'region' => 'Lima',
            'city' => 'Lima',
            'district' => 'Miraflores',
            'address_line_1' => 'Av. Example 123',
            'address_line_2' => null,
            'postal_code' => '15074',
            'reference' => null,
            'is_default_shipping' => false,
            'is_default_billing' => false,
        ];
    }
}
