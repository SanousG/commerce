<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Tenancy\Application\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

class AccessPageTest extends AccessFeatureTestCase
{
    public function test_access_page_requires_authentication_and_verification(): void
    {
        $this->get(route('admin.access.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('admin.access.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_access_page_shows_only_current_tenant_permissions(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->createMembership($user, $tenantA, SystemRole::Admin);
        $this->createMembership($user, $tenantB, SystemRole::Member);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])
            ->get(route('admin.access.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/access/Index')
                ->where('tenant.name', $tenantA->name)
                ->where('currentRole', SystemRole::Admin->value)
                ->where('permissions', fn ($permissions) => collect($permissions)
                    ->contains('stores.create') && ! collect($permissions)->contains('tenant.manage'))
                ->has('roles', 3));

        $this->post(route('admin.tenants.select', $tenantB))
            ->assertSessionHas(TenantContext::SESSION_KEY, $tenantB->getKey());

        $this->get(route('admin.access.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.name', $tenantB->name)
                ->where('currentRole', SystemRole::Member->value)
                ->where('permissions', fn ($permissions) => collect($permissions)
                    ->contains('stores.select') && ! collect($permissions)->contains('stores.create')));
    }

    public function test_shared_membership_contains_only_role_status_and_permission_keys(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenant();
        $this->createMembership($user, $tenant, SystemRole::Member);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.access.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('currentTenantMembership', 3)
                ->where('currentTenantMembership.role', SystemRole::Member->value)
                ->where('currentTenantMembership.status', 'ACTIVE')
                ->where('currentTenantMembership.permissions', fn ($permissions) => collect($permissions)
                    ->every(fn ($permission) => is_string($permission))));
    }
}
