<?php

namespace Tests\Feature\AdminSettings;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\DeactivateTenantMember;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

class TeamManagementTest extends AdminSettingsFeatureTestCase
{
    public function test_owner_changes_member_to_admin_and_permissions_apply_immediately(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $user = User::factory()->create();
        $member = $this->createMembership($user, $tenant, SystemRole::Member);

        $this->actingAs($owner)->withSession($this->tenantSession($tenant))
            ->patch(route('admin.settings.team.members.role', $member), ['role_id' => $this->role(SystemRole::Admin)->getKey()])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->withSession($this->tenantSession($tenant))
            ->get(route('admin.settings.index'))->assertOk();
    }

    public function test_deactivated_member_loses_current_tenant_context_and_reactivation_restores_access(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $user = User::factory()->create();
        $member = $this->createMembership($user, $tenant, SystemRole::Admin);

        $this->actingAs($owner)->withSession($this->tenantSession($tenant))
            ->post(route('admin.settings.team.members.deactivate', $member))->assertSessionHasNoErrors();
        $this->assertSame(TenantMemberStatus::Suspended, $member->refresh()->status);

        $this->actingAs($user)->withSession($this->tenantSession($tenant))
            ->get(route('admin.settings.index'))->assertRedirect(route('admin.tenants.index'))
            ->assertSessionMissing(TenantContext::SESSION_KEY);

        $this->actingAs($owner)->withSession($this->tenantSession($tenant))
            ->post(route('admin.settings.team.members.reactivate', $member))->assertSessionHasNoErrors();
        $this->actingAs($user)->withSession($this->tenantSession($tenant))
            ->get(route('admin.settings.index'))->assertOk();
    }

    public function test_last_owner_cannot_be_deactivated_or_downgraded(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $membership = $owner->tenantMemberships()->where('tenant_id', $tenant->getKey())->firstOrFail();

        $this->actingAs($owner)->withSession($this->tenantSession($tenant));
        $this->post(route('admin.settings.team.members.deactivate', $membership))->assertSessionHasErrors('member');
        $this->patch(route('admin.settings.team.members.role', $membership), ['role_id' => $this->role(SystemRole::Admin)->getKey()])
            ->assertSessionHasErrors('member');

        $this->assertSame(SystemRole::Owner->value, $membership->fresh('role')->role->key);
        $this->assertSame(TenantMemberStatus::Active, $membership->refresh()->status);
    }

    public function test_owner_can_downgrade_another_owner_when_one_active_owner_remains(): void
    {
        $tenant = $this->createTenant();
        $ownerA = $this->ownerIn($tenant);
        $ownerB = User::factory()->create();
        $membershipB = $this->createMembership($ownerB, $tenant, SystemRole::Owner);

        $this->actingAs($ownerA)->withSession($this->tenantSession($tenant))
            ->patch(route('admin.settings.team.members.role', $membershipB), ['role_id' => $this->role(SystemRole::Admin)->getKey()])
            ->assertSessionHasNoErrors();

        $this->assertSame(SystemRole::Admin->value, $membershipB->fresh('role')->role->key);
    }

    public function test_self_role_change_and_self_deactivation_are_blocked(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $membership = $owner->tenantMemberships()->where('tenant_id', $tenant->getKey())->firstOrFail();
        $otherOwner = User::factory()->create();
        $this->createMembership($otherOwner, $tenant, SystemRole::Owner);

        $this->actingAs($owner)->withSession($this->tenantSession($tenant));
        $this->patch(route('admin.settings.team.members.role', $membership), ['role_id' => $this->role(SystemRole::Admin)->getKey()])
            ->assertSessionHasErrors('member');
        $this->post(route('admin.settings.team.members.deactivate', $membership))->assertSessionHasErrors('member');
    }

    public function test_admin_can_view_team_but_cannot_mutate_it(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $admin = User::factory()->create();
        $this->createMembership($admin, $tenant, SystemRole::Admin);
        $ownerMembership = $owner->tenantMemberships()->where('tenant_id', $tenant->getKey())->firstOrFail();

        $this->actingAs($admin)->withSession($this->tenantSession($tenant))
            ->get(route('admin.settings.team.index'))->assertOk();
        $this->post(route('admin.settings.team.members.deactivate', $ownerMembership))->assertForbidden();
    }

    public function test_privileged_owner_cannot_mutate_another_tenants_member(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $ownerA = $this->ownerIn($tenantA);
        $ownerB = $this->ownerIn($tenantB);
        $membershipB = $ownerB->tenantMemberships()->where('tenant_id', $tenantB->getKey())->firstOrFail();

        $this->actingAs($ownerA)->withSession($this->tenantSession($tenantA))
            ->post(route('admin.settings.team.members.deactivate', $membershipB))->assertNotFound();
    }

    public function test_team_is_tenant_wide_and_does_not_change_with_selected_store(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $storeA = $this->storeFor($tenant);
        $storeB = $this->storeFor($tenant);

        foreach ([$storeA, $storeB] as $store) {
            $this->actingAs($owner)->withSession($this->tenantSession($tenant) + [StoreContext::SESSION_KEY => $store->getKey()])
                ->get(route('admin.settings.team.index'))->assertInertia(fn (Assert $page) => $page
                ->component('admin/settings/Team')->has('members', 1));
        }
    }

    public function test_owner_lock_strategy_leaves_an_active_owner_after_sequential_competing_operations(): void
    {
        $tenant = $this->createTenant();
        $ownerA = $this->ownerIn($tenant);
        $ownerB = User::factory()->create();
        $membershipB = $this->createMembership($ownerB, $tenant, SystemRole::Owner);
        $membershipA = $ownerA->tenantMemberships()->where('tenant_id', $tenant->getKey())->firstOrFail();

        $this->actingAs($ownerA)->withSession($this->tenantSession($tenant))
            ->post(route('admin.settings.team.members.deactivate', $membershipB))->assertSessionHasNoErrors();

        try {
            app(DeactivateTenantMember::class)->handle($tenant, $ownerB, $membershipA->getKey());
            $this->fail('The last owner operation should have been rejected.');
        } catch (ValidationException) {
            // The tenant row lock serializes owner mutations before this count is checked.
        }

        $this->assertDatabaseHas('tenant_members', ['id' => $membershipA->getKey(), 'status' => 'ACTIVE']);
    }
}
