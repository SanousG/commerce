<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_active_tenant_is_sent_to_creation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.tenants.create'));

        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    public function test_single_active_membership_is_selected_and_sets_request_context(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createMembership($user);
        $store = $this->createStore($tenant);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSessionHas(TenantContext::SESSION_KEY, $tenant->getKey())
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('currentTenant.id', $tenant->getKey())
                ->where('currentTenant.name', $tenant->name)
                ->where('currentTenantMembership.role', SystemRole::Owner->value)
                ->where('currentStore.id', $store->getKey()));

        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    public function test_multiple_active_memberships_require_selection(): void
    {
        $user = User::factory()->create();
        $this->createMembership($user);
        $this->createMembership($user, SystemRole::Admin);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.tenants.index'))
            ->assertSessionMissing(TenantContext::SESSION_KEY);

        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    public function test_invalid_session_tenant_is_cleared_without_setting_context(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tenantB = $this->createMembership($userB);

        $this->actingAs($userA)
            ->withSession([TenantContext::SESSION_KEY => $tenantB->getKey()])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.tenants.index'))
            ->assertSessionMissing(TenantContext::SESSION_KEY);

        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    public function test_context_does_not_leak_between_requests(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $tenantA = $this->createMembership($userA);
        $this->createStore($tenantA);

        $this->actingAs($userA)
            ->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertFalse(app(TenantContext::class)->hasTenant());

        session()->forget(TenantContext::SESSION_KEY);

        $this->actingAs($userB)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.tenants.create'));

        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    public function test_selected_tenant_is_invalidated_when_membership_loses_access(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createMembership($user);
        $store = $this->createStore($tenant);
        $user->tenantMemberships()->sole()->update(['status' => TenantMemberStatus::Suspended]);

        $this->actingAs($user)
            ->withSession([
                TenantContext::SESSION_KEY => $tenant->getKey(),
                StoreContext::SESSION_KEY => $store->getKey(),
            ])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.tenants.index'))
            ->assertSessionMissing(TenantContext::SESSION_KEY)
            ->assertSessionMissing(StoreContext::SESSION_KEY);

        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    public function test_selected_tenant_is_invalidated_when_tenant_is_archived(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createMembership($user);
        $store = $this->createStore($tenant);
        $tenant->update(['status' => TenantStatus::Archived]);

        $this->actingAs($user)
            ->withSession([
                TenantContext::SESSION_KEY => $tenant->getKey(),
                StoreContext::SESSION_KEY => $store->getKey(),
            ])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.tenants.index'))
            ->assertSessionMissing(TenantContext::SESSION_KEY)
            ->assertSessionMissing(StoreContext::SESSION_KEY);

        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    public function test_context_is_cleared_when_downstream_request_throws(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createMembership($user);

        Route::get('/_test/tenant-context-failure', fn () => throw new RuntimeException('Expected failure'))
            ->middleware(['web', 'auth', 'verified', 'tenant']);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)
                ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
                ->get('/_test/tenant-context-failure');
            $this->fail('The test route should have thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Expected failure', $exception->getMessage());
        }

        $this->assertFalse(app(TenantContext::class)->hasTenant());
    }

    private function createMembership(
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
