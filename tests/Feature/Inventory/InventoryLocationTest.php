<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Inventory\Application\CreateDefaultInventoryLocation;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Tenancy\Application\CreateTenant;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\QueryException;
use RuntimeException;

class InventoryLocationTest extends InventoryFeatureTestCase
{
    public function test_new_tenant_gets_normalized_configured_default_location(): void
    {
        config()->set('commerce.default_inventory_location_name', ' Main warehouse ');
        config()->set('commerce.default_inventory_location_code', ' main_01 ');

        $tenant = app(CreateTenant::class)->handle(User::factory()->create(), 'Inventory tenant');
        $location = $tenant->defaultInventoryLocation()->sole();

        $this->assertSame('Main warehouse', $location->name);
        $this->assertSame('MAIN_01', $location->code);
        $this->assertSame(InventoryLocationStatus::Active, $location->status);
        $this->assertTrue($location->is_default);
    }

    public function test_invalid_inventory_bootstrap_rolls_back_tenant_membership_and_pricing(): void
    {
        config()->set('commerce.default_inventory_location_code', 'invalid code');

        try {
            app(CreateTenant::class)->handle(User::factory()->create(), 'Must Roll Back Inventory');
            $this->fail('Invalid inventory configuration should fail.');
        } catch (RuntimeException) {
            $this->assertDatabaseMissing('tenants', ['name' => 'Must Roll Back Inventory']);
            $this->assertDatabaseCount('tenant_members', 0);
            $this->assertDatabaseCount('price_lists', 0);
            $this->assertDatabaseCount('inventory_locations', 0);
        }
    }

    public function test_default_creation_is_idempotent(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $first = $tenant->defaultInventoryLocation()->sole();
        $second = app(CreateDefaultInventoryLocation::class)->handle($tenant);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $tenant->inventoryLocations()->where('is_default', true)->count());
    }

    public function test_owner_creates_location_for_active_tenant_and_input_tenant_id_is_ignored(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $other = $this->createTenantFor(User::factory()->create());

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.inventory.locations.store'), [
                'name' => ' Secondary ', 'code' => ' sec_01 ', 'status' => 'ACTIVE',
                'tenant_id' => $other->getKey(),
            ])->assertRedirect(route('admin.inventory.locations.index'));

        $this->assertDatabaseHas('inventory_locations', [
            'tenant_id' => $tenant->getKey(), 'name' => 'Secondary', 'code' => 'SEC_01', 'is_default' => false,
        ]);
        $this->assertDatabaseMissing('inventory_locations', [
            'tenant_id' => $other->getKey(), 'code' => 'SEC_01',
        ]);
    }

    public function test_location_code_is_case_insensitively_unique_per_tenant_but_reusable_between_tenants(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $this->createLocation($tenantA, ['code' => 'PICKUP']);
        $this->createLocation($tenantB, ['code' => 'PICKUP']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])
            ->post(route('admin.inventory.locations.store'), [
                'name' => 'Duplicate', 'code' => 'pickup', 'status' => 'ACTIVE',
            ])->assertSessionHasErrors('code');
    }

    public function test_default_can_change_atomically_only_to_an_active_location(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $original = $tenant->defaultInventoryLocation()->sole();
        $replacement = $this->createLocation($tenant);
        $inactive = $this->createLocation($tenant, ['status' => 'INACTIVE']);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->post(route('admin.inventory.locations.make-default', $replacement))->assertRedirect();
        $this->assertFalse($original->fresh()->is_default);
        $this->assertTrue($replacement->fresh()->is_default);
        $this->assertSame(1, $tenant->inventoryLocations()->where('is_default', true)->count());

        $this->post(route('admin.inventory.locations.make-default', $inactive))
            ->assertSessionHasErrors('location');
        $this->assertTrue($replacement->fresh()->is_default);
    }

    public function test_default_cannot_be_made_inactive_and_non_default_status_can_change(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $default = $tenant->defaultInventoryLocation()->sole();
        $other = $this->createLocation($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->put(route('admin.inventory.locations.update', $default), [
            'name' => $default->name, 'code' => $default->code, 'status' => 'INACTIVE',
        ])->assertSessionHasErrors('status');
        $this->put(route('admin.inventory.locations.update', $other), [
            'name' => 'Closed depot', 'code' => $other->code, 'status' => 'ARCHIVED',
        ])->assertRedirect();

        $this->assertSame(InventoryLocationStatus::Active, $default->fresh()->status);
        $this->assertSame(InventoryLocationStatus::Archived, $other->fresh()->status);
    }

    public function test_admin_manages_locations_while_member_can_only_view(): void
    {
        $admin = User::factory()->create();
        $adminTenant = $this->createTenantFor($admin, SystemRole::Admin);
        $this->actingAs($admin)->withSession([TenantContext::SESSION_KEY => $adminTenant->getKey()])
            ->post(route('admin.inventory.locations.store'), [
                'name' => 'Admin depot', 'code' => 'ADMIN', 'status' => 'ACTIVE',
            ])->assertRedirect();

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $default = $memberTenant->defaultInventoryLocation()->sole();
        $this->actingAs($member)->withSession([TenantContext::SESSION_KEY => $memberTenant->getKey()]);

        $this->get(route('admin.inventory.locations.index'))->assertOk();
        $this->get(route('admin.inventory.locations.create'))->assertForbidden();
        $this->post(route('admin.inventory.locations.store'), [
            'name' => 'Denied', 'code' => 'DENIED', 'status' => 'ACTIVE',
        ])->assertForbidden();
        $this->put(route('admin.inventory.locations.update', $default), [
            'name' => 'Denied', 'code' => 'DEFAULT', 'status' => 'ACTIVE',
        ])->assertForbidden();
        $this->post(route('admin.inventory.locations.make-default', $default))->assertForbidden();
    }

    public function test_cross_tenant_locations_are_hidden_from_edit_update_and_default_actions(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $foreign = $this->createLocation($this->createTenantFor(User::factory()->create()));
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->get(route('admin.inventory.locations.edit', $foreign))->assertNotFound();
        $this->put(route('admin.inventory.locations.update', $foreign), [
            'name' => 'Attack', 'code' => 'ATTACK', 'status' => 'ACTIVE',
        ])->assertNotFound();
        $this->post(route('admin.inventory.locations.make-default', $foreign))->assertNotFound();
    }

    public function test_database_rejects_removing_the_only_active_default(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());

        $this->expectException(QueryException::class);
        $tenant->defaultInventoryLocation()->sole()->update(['is_default' => false]);
    }
}
