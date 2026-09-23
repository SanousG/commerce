<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AccessFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createTenant(): Tenant
    {
        return Tenant::create([
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
            'status' => TenantStatus::Active,
        ]);
    }

    protected function createMembership(
        User $user,
        Tenant $tenant,
        SystemRole $role = SystemRole::Member,
        TenantMemberStatus $status = TenantMemberStatus::Active,
    ): TenantMember {
        return TenantMember::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => $this->role($role)->getKey(),
            'status' => $status,
            'joined_at' => $status === TenantMemberStatus::Active ? now() : null,
        ]);
    }

    protected function role(SystemRole $role): Role
    {
        return Role::query()->system($role)->sole();
    }
}
