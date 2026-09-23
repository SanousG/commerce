<?php

namespace Tests\Feature\Stores;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;

class StoreContextTest extends StoreFeatureTestCase
{
    public function test_zero_active_stores_redirects_to_creation(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.stores.create'));

        $this->assertFalse(app(StoreContext::class)->hasStore());
    }

    public function test_member_without_active_stores_is_sent_to_store_list(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, SystemRole::Member);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.stores.index'));

        $this->get(route('admin.stores.index'))->assertOk();
    }

    public function test_single_active_store_is_selected_and_sets_request_context(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSessionHas(StoreContext::SESSION_KEY, $store->getKey())
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Dashboard')
                ->where('currentStore.id', $store->getKey())
                ->where('currentStore.name', $store->name));

        $this->assertFalse(app(StoreContext::class)->hasStore());
    }

    public function test_multiple_active_stores_require_selection(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->createStore($tenant);
        $this->createStore($tenant);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.stores.index'))
            ->assertSessionMissing(StoreContext::SESSION_KEY);

        $this->assertFalse(app(StoreContext::class)->hasStore());
    }

    public function test_invalid_cross_tenant_session_store_is_replaced_safely(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $storeA = $this->createStore($tenantA);
        $storeB = $this->createStore($tenantB);

        $this->actingAs($user)
            ->withSession([
                TenantContext::SESSION_KEY => $tenantA->getKey(),
                StoreContext::SESSION_KEY => $storeB->getKey(),
            ])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSessionHas(StoreContext::SESSION_KEY, $storeA->getKey())
            ->assertInertia(fn (Assert $page) => $page->where('currentStore.id', $storeA->getKey()));

        $this->assertFalse(app(StoreContext::class)->hasStore());
    }

    public function test_inactive_selected_store_is_replaced_by_only_active_store(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $inactiveStore = $this->createStore($tenant, StoreStatus::Inactive);
        $activeStore = $this->createStore($tenant);

        $this->actingAs($user)
            ->withSession([
                TenantContext::SESSION_KEY => $tenant->getKey(),
                StoreContext::SESSION_KEY => $inactiveStore->getKey(),
            ])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSessionHas(StoreContext::SESSION_KEY, $activeStore->getKey())
            ->assertInertia(fn (Assert $page) => $page->where('currentStore.id', $activeStore->getKey()));
    }

    public function test_store_context_does_not_leak_between_requests(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        $this->actingAs($user)
            ->withSession([
                TenantContext::SESSION_KEY => $tenant->getKey(),
                StoreContext::SESSION_KEY => $store->getKey(),
            ])
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertFalse(app(StoreContext::class)->hasStore());

        session()->forget(StoreContext::SESSION_KEY);
        $store->update(['status' => 'INACTIVE']);

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.stores.create'));
        $this->assertFalse(app(StoreContext::class)->hasStore());
    }

    public function test_store_context_is_cleared_when_downstream_request_throws(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);

        Route::get('/_test/store-context-failure', fn () => throw new RuntimeException('Expected store failure'))
            ->middleware(['web', 'auth', 'verified', 'tenant', 'store']);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)
                ->withSession([
                    TenantContext::SESSION_KEY => $tenant->getKey(),
                    StoreContext::SESSION_KEY => $store->getKey(),
                ])
                ->get('/_test/store-context-failure');
            $this->fail('The test route should have thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Expected store failure', $exception->getMessage());
        }

        $this->assertFalse(app(StoreContext::class)->hasStore());
    }
}
