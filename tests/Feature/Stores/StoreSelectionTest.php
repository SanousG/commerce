<?php

namespace Tests\Feature\Stores;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

class StoreSelectionTest extends StoreFeatureTestCase
{
    public function test_listing_only_contains_stores_from_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $storeA = $this->createStore($tenantA);
        $this->createStore($tenantA, StoreStatus::Inactive);
        $this->createStore($tenantB);

        $this->actingAs($user)
            ->withSession([
                TenantContext::SESSION_KEY => $tenantA->getKey(),
                StoreContext::SESSION_KEY => $storeA->getKey(),
            ])
            ->get(route('admin.stores.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/stores/Index')
                ->has('stores', 2)
                ->where('stores.0.selected', fn ($selected) => is_bool($selected))
                ->where('stores', fn ($stores) => collect($stores)->every(
                    fn ($store) => in_array($store['id'], $tenantA->stores()->pluck('id')->all(), true)
                )));
    }

    public function test_user_can_select_active_store_in_current_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.stores.select', $store->getKey()))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas(StoreContext::SESSION_KEY, $store->getKey());
    }

    public function test_cross_tenant_and_unknown_store_selection_return_not_found(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $storeB = $this->createStore($tenantB);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);

        $this->post(route('admin.stores.select', $storeB->getKey()))->assertNotFound();
        $this->post(route('admin.stores.select', (string) Str::uuid()))
            ->assertNotFound()
            ->assertSessionMissing(StoreContext::SESSION_KEY);
    }

    public function test_malformed_store_identifier_returns_not_found(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post('/admin/stores/not-a-uuid/select')
            ->assertNotFound();
    }

    public function test_inactive_and_archived_stores_cannot_be_selected(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);

        foreach ([StoreStatus::Inactive, StoreStatus::Archived] as $status) {
            $store = $this->createStore($tenant, $status);

            $this->actingAs($user)
                ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
                ->post(route('admin.stores.select', $store->getKey()))
                ->assertNotFound();
        }
    }

    public function test_user_can_switch_between_stores(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->post(route('admin.stores.select', $storeA->getKey()))
            ->assertSessionHas(StoreContext::SESSION_KEY, $storeA->getKey());
        $this->post(route('admin.stores.select', $storeB->getKey()))
            ->assertSessionHas(StoreContext::SESSION_KEY, $storeB->getKey());
    }

    public function test_switching_tenant_clears_store_and_resolves_within_new_tenant(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user, SystemRole::Owner);
        $tenantB = $this->createTenantFor($user, SystemRole::Admin);
        $storeA = $this->createStore($tenantA);
        $storeB = $this->createStore($tenantB);

        $this->actingAs($user)
            ->withSession([
                TenantContext::SESSION_KEY => $tenantA->getKey(),
                StoreContext::SESSION_KEY => $storeA->getKey(),
            ])
            ->post(route('admin.tenants.select', $tenantB->getKey()))
            ->assertSessionHas(TenantContext::SESSION_KEY, $tenantB->getKey())
            ->assertSessionMissing(StoreContext::SESSION_KEY);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSessionHas(StoreContext::SESSION_KEY, $storeB->getKey())
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentTenant.id', $tenantB->getKey())
                ->where('currentStore.id', $storeB->getKey()));
    }
}
