<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Application\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

class CustomerManagementTest extends CustomersFeatureTestCase
{
    public function test_owner_and_admin_can_create_while_member_is_view_only(): void
    {
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $user = User::factory()->create();
            $tenant = $this->createTenantFor($user, $role);
            $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
                ->post(route('admin.customers.store'), $this->customerPayload())->assertRedirect();
            $this->assertTrue($tenant->customers()->exists());
        }

        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        $customer = $this->createCustomer($tenant);
        $this->actingAs($member)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->get(route('admin.customers.index'))->assertOk();
        $this->get(route('admin.customers.edit', $customer))->assertOk();
        $this->get(route('admin.customers.create'))->assertForbidden();
        $this->post(route('admin.customers.store'), $this->customerPayload())->assertForbidden();
        $this->put(route('admin.customers.update', $customer), [...$this->customerPayload(), 'status' => 'ACTIVE'])->assertForbidden();
        $this->post(route('admin.customers.archive', $customer))->assertForbidden();
    }

    public function test_creation_normalizes_identity_ignores_tenant_id_and_allows_missing_email_or_phone(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $foreign = $this->createTenantFor(User::factory()->create());
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->post(route('admin.customers.store'), [
            ...$this->customerPayload(), 'tenant_id' => $foreign->getKey(),
            'email' => '  ADA@EXAMPLE.TEST ', 'phone' => ' +51 (999) 111-222 ',
        ])->assertRedirect();
        $customer = Customer::query()->sole();
        $this->assertSame($tenant->getKey(), $customer->tenant_id);
        $this->assertSame('ada@example.test', $customer->email);
        $this->assertSame('+51999111222', $customer->phone);

        $this->post(route('admin.customers.store'), [
            'first_name' => 'Email-less', 'last_name' => '', 'email' => '', 'phone' => '+12025550123', 'notes' => '',
        ])->assertRedirect();
        $this->post(route('admin.customers.store'), [
            'first_name' => 'Phone-less', 'last_name' => '', 'email' => 'phone-less@example.test', 'phone' => '', 'notes' => '',
        ])->assertRedirect();
    }

    public function test_empty_and_duplicate_identities_are_rejected_per_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->createCustomer($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->post(route('admin.customers.store'), ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'notes' => 'Only notes'])
            ->assertSessionHasErrors('first_name');
        $this->post(route('admin.customers.store'), [...$this->customerPayload(), 'email' => 'ADA@example.test', 'phone' => '+12025550001'])
            ->assertSessionHasErrors('email');
        $this->post(route('admin.customers.store'), [...$this->customerPayload(), 'email' => 'other@example.test'])
            ->assertSessionHasErrors('phone');

        $otherUser = User::factory()->create();
        $otherTenant = $this->createTenantFor($otherUser);
        $this->actingAs($otherUser)->withSession([TenantContext::SESSION_KEY => $otherTenant->getKey()])
            ->post(route('admin.customers.store'), $this->customerPayload())->assertRedirect();
    }

    public function test_update_archive_cross_tenant_and_no_delete_route(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $local = $this->createCustomer($tenant);
        $foreign = $this->createCustomer($foreignTenant, ['email' => 'foreign@example.test', 'phone' => '+12025550100']);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->put(route('admin.customers.update', $local), [...$this->customerPayload(), 'first_name' => 'Grace', 'status' => 'BLOCKED'])
            ->assertRedirect();
        $this->assertSame('Grace', $local->fresh()->first_name);
        $this->assertSame('BLOCKED', $local->fresh()->status->value);
        $this->get(route('admin.customers.edit', $foreign))->assertNotFound();
        $this->put(route('admin.customers.update', $foreign), [...$this->customerPayload(), 'status' => 'ACTIVE'])->assertNotFound();
        $this->post(route('admin.customers.archive', $foreign))->assertNotFound();
        $this->post(route('admin.customers.archive', $local))->assertRedirect(route('admin.customers.index'));
        $this->assertSame('ARCHIVED', $local->fresh()->status->value);
        $this->delete('/admin/customers/'.$local->getKey())->assertMethodNotAllowed();
    }

    public function test_search_pagination_tenant_switch_and_store_switch_are_isolated(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $this->createCustomer($tenantA, ['first_name' => 'VisibleName', 'last_name' => 'SearchLast', 'email' => 'visible@example.test', 'phone' => '+12025550111']);
        $this->createCustomer($tenantB, ['first_name' => 'SecretName', 'email' => 'secret@example.test', 'phone' => '+12025550112']);
        for ($i = 0; $i < 20; $i++) {
            $this->createCustomer($tenantA, ['first_name' => "Page{$i}", 'email' => "page{$i}@example.test", 'phone' => '+1303555'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)]);
        }
        Store::create(['tenant_id' => $tenantA->getKey(), 'name' => 'Store A', 'slug' => 'store-a', 'status' => StoreStatus::Active, 'currency' => 'PEN', 'timezone' => 'America/Lima', 'locale' => 'es_PE']);
        $this->actingAs($user);

        foreach (['VisibleName', 'SearchLast', 'visible@example', '12025550111'] as $search) {
            $this->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])->get(route('admin.customers.index', ['search' => $search]))
                ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.display_name', 'VisibleName SearchLast'));
        }
        $this->withSession([TenantContext::SESSION_KEY => $tenantA->getKey(), 'current_store_id' => Store::query()->sole()->getKey()])
            ->get(route('admin.customers.index'))->assertInertia(fn (Assert $page) => $page->where('customers.total', 21)->where('customers.per_page', 20)->where('customers.last_page', 2));
        $this->withSession([TenantContext::SESSION_KEY => $tenantB->getKey()])->get(route('admin.customers.index'))
            ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.display_name', 'SecretName Lovelace'));
    }
}
