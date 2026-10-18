<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Permission;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PermissionCheckerTest extends AccessFeatureTestCase
{
    public function test_owner_admin_and_member_have_expected_permissions(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenant();
        $context = app(TenantContext::class);
        $checker = app(PermissionChecker::class);
        $context->set($tenant);

        try {
            $owner = $this->createMembership($user, $tenant, SystemRole::Owner);
            $this->assertTrue($checker->can($owner, PermissionKey::TenantManage));
            $this->assertTrue($checker->can($owner, PermissionKey::MembersManage));
            $owner->delete();

            $admin = $this->createMembership($user, $tenant, SystemRole::Admin);
            $this->assertTrue($checker->can($admin, PermissionKey::StoresCreate));
            $this->assertFalse($checker->can($admin, PermissionKey::TenantManage));
            $admin->delete();

            $member = $this->createMembership($user, $tenant, SystemRole::Member);
            $this->assertTrue($checker->can($member, PermissionKey::StoresSelect));
            $this->assertFalse($checker->can($member, PermissionKey::StoresCreate));
            $this->assertFalse($checker->can($member, 'unknown.permission'));
        } finally {
            $context->clear();
        }
    }

    public function test_gate_uses_the_current_active_membership(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenant();
        $this->createMembership($user, $tenant, SystemRole::Admin);
        $context = app(TenantContext::class);
        $context->set($tenant);

        try {
            $this->assertTrue(Gate::forUser($user)->allows(PermissionKey::StoresCreate->value));
            $this->assertFalse(Gate::forUser($user)->allows(PermissionKey::TenantManage->value));
        } finally {
            $context->clear();
        }
    }

    public function test_permissions_fail_closed_without_a_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenant();
        $membership = $this->createMembership($user, $tenant, SystemRole::Owner);

        $this->assertFalse(app(PermissionChecker::class)->can($membership, PermissionKey::StoresCreate));
        $this->assertFalse(Gate::forUser($user)->allows(PermissionKey::StoresCreate->value));
    }

    public function test_current_membership_is_loaded_only_once_per_request_scope(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenant();
        $this->createMembership($user, $tenant, SystemRole::Owner);
        $context = app(TenantContext::class);
        $checker = app(PermissionChecker::class);
        $context->set($tenant);

        try {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $first = $checker->currentMembership($user);
            $firstQueryCount = count(DB::getQueryLog());
            $second = $checker->currentMembership($user);

            $this->assertNotNull($first);
            $this->assertSame($first, $second);
            $this->assertGreaterThan(0, $firstQueryCount);
            $this->assertCount($firstQueryCount, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            $context->clear();
        }
    }

    public function test_suspended_and_removed_memberships_have_no_permissions(): void
    {
        $user = User::factory()->create();
        $checker = app(PermissionChecker::class);
        $context = app(TenantContext::class);

        foreach ([TenantMemberStatus::Suspended, TenantMemberStatus::Removed] as $status) {
            $tenant = $this->createTenant();
            $membership = $this->createMembership($user, $tenant, SystemRole::Owner, $status);
            $context->set($tenant);
            $this->assertFalse($checker->can($membership, PermissionKey::StoresCreate));
            $context->clear();
        }
    }

    public function test_membership_cannot_authorize_another_tenant(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $membershipA = $this->createMembership($user, $tenantA, SystemRole::Admin);
        $this->createMembership($user, $tenantB);

        $context = app(TenantContext::class);
        $context->set($tenantB);

        try {
            $this->assertFalse(app(PermissionChecker::class)->can($membershipA, PermissionKey::StoresCreate));
        } finally {
            $context->clear();
        }
    }

    public function test_tenant_owned_role_cannot_be_assigned_to_a_membership(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $permission = Permission::query()->where('key', PermissionKey::StoresCreate)->sole();
        $customRoleA = Role::create([
            'tenant_id' => $tenantA->getKey(),
            'name' => 'Tenant A role',
            'key' => 'TENANT_A_ROLE',
            'is_system' => false,
        ]);
        $customRoleA->permissions()->attach($permission);
        $membershipB = $this->createMembership($user, $tenantB);

        $this->expectException(QueryException::class);
        $membershipB->update(['role_id' => $customRoleA->getKey()]);
    }
}
