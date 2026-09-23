<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_lists_tenants_with_an_active_membership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $visibleTenant = $this->createMembership($user);
        $this->createMembership($user, SystemRole::Member, TenantMemberStatus::Suspended);
        $this->createMembership(
            $user,
            SystemRole::Member,
            TenantMemberStatus::Active,
            TenantStatus::Archived,
        );
        $this->createMembership($otherUser);

        $this->actingAs($user)
            ->get(route('admin.tenants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tenants/Index')
                ->has('tenants', 1)
                ->where('tenants.0.id', $visibleTenant->getKey())
                ->where('tenants.0.role', SystemRole::Member->value));
    }

    public function test_user_can_select_tenant_with_active_membership(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createMembership($user, SystemRole::Admin);

        $this->actingAs($user)
            ->post(route('admin.tenants.select', $tenant))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas(TenantContext::SESSION_KEY, $tenant->getKey());
    }

    public function test_user_cannot_select_another_users_tenant(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tenantB = $this->createMembership($userB);

        $this->actingAs($userA)
            ->post(route('admin.tenants.select', $tenantB))
            ->assertForbidden()
            ->assertSessionMissing(TenantContext::SESSION_KEY);
    }

    public function test_malformed_tenant_identifier_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/tenants/not-a-uuid/select')
            ->assertNotFound();
    }

    public function test_user_cannot_select_tenant_with_non_active_membership(): void
    {
        $user = User::factory()->create();

        foreach ([TenantMemberStatus::Suspended, TenantMemberStatus::Removed] as $status) {
            $tenant = $this->createMembership($user, SystemRole::Member, $status);

            $this->actingAs($user)
                ->post(route('admin.tenants.select', $tenant))
                ->assertForbidden();
        }
    }

    public function test_user_cannot_select_non_active_tenant(): void
    {
        $user = User::factory()->create();

        foreach ([TenantStatus::Suspended, TenantStatus::Archived] as $status) {
            $tenant = $this->createMembership(
                $user,
                SystemRole::Member,
                TenantMemberStatus::Active,
                $status,
            );

            $this->actingAs($user)
                ->post(route('admin.tenants.select', $tenant))
                ->assertForbidden();
        }
    }

    public function test_user_can_switch_between_tenants_with_different_roles(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createMembership($user, SystemRole::Owner);
        $tenantB = $this->createMembership($user, SystemRole::Member);
        $this->createStore($tenantA);
        $this->createStore($tenantB);

        $this->actingAs($user)
            ->post(route('admin.tenants.select', $tenantA))
            ->assertSessionHas(TenantContext::SESSION_KEY, $tenantA->getKey());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentTenant.id', $tenantA->getKey())
                ->where('currentTenantMembership.role', SystemRole::Owner->value));

        $this->post(route('admin.tenants.select', $tenantB))
            ->assertSessionHas(TenantContext::SESSION_KEY, $tenantB->getKey());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentTenant.id', $tenantB->getKey())
                ->where('currentTenantMembership.role', SystemRole::Member->value));
    }

    private function createMembership(
        User $user,
        SystemRole $role = SystemRole::Member,
        TenantMemberStatus $membershipStatus = TenantMemberStatus::Active,
        TenantStatus $tenantStatus = TenantStatus::Active,
    ): Tenant {
        $tenant = Tenant::create([
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
            'status' => $tenantStatus,
        ]);

        TenantMember::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->system($role)->sole()->getKey(),
            'status' => $membershipStatus,
            'joined_at' => $membershipStatus === TenantMemberStatus::Active ? now() : null,
        ]);

        return $tenant;
    }

    private function createStore(Tenant $tenant): Store
    {
        return Store::create([
            'tenant_id' => $tenant->getKey(),
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
            'status' => StoreStatus::Active,
            'currency' => 'PEN',
            'timezone' => 'America/Lima',
            'locale' => 'es_PE',
        ]);
    }
}
