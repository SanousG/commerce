<?php

namespace Tests\Feature\Stores;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class StoreFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createTenantFor(
        User $user,
        SystemRole $role = SystemRole::Owner,
    ): Tenant {
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createStore(
        Tenant $tenant,
        StoreStatus $status = StoreStatus::Active,
        array $attributes = [],
    ): Store {
        $name = $attributes['name'] ?? fake()->unique()->company();

        return Store::create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'slug' => $attributes['slug'] ?? Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => $status,
            'currency' => $attributes['currency'] ?? 'PEN',
            'timezone' => $attributes['timezone'] ?? 'America/Lima',
            'locale' => $attributes['locale'] ?? 'es_PE',
        ]);
    }
}
