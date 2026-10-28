<?php

namespace Tests\Feature\Stores;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use App\Modules\Tenancy\Application\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

class StoreCreationTest extends StoreFeatureTestCase
{
    public function test_owner_can_view_creation_page_and_create_first_store(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, SystemRole::Owner);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.stores.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/stores/Create'));

        $this->post(route('admin.stores.store'), [
            'name' => 'Lima Central',
            'currency' => 'PEN',
            'timezone' => 'America/Lima',
            'locale' => 'es_PE',
        ])->assertRedirect(route('admin.dashboard'));

        $store = Store::query()->sole();

        $this->assertSame($tenant->getKey(), $store->tenant_id);
        $this->assertSame(StoreStatus::Active, $store->status);
        $this->assertSame('lima-central', $store->slug);
        $this->assertSame($store->getKey(), session(StoreContext::SESSION_KEY));
        $this->assertTrue($tenant->stores()->whereKey($store->getKey())->exists());
        $this->assertTrue($store->tenant->is($tenant));
        $publication = $store->storefrontPublication()->sole();
        $this->assertSame(StorefrontPublicationStatus::Unpublished, $publication->status);
        $this->assertSame('lima-central', $publication->public_handle);
        $this->assertNull($publication->published_at);
    }

    public function test_automatic_public_handles_are_global_collision_safe_and_never_publish(): void
    {
        $firstUser = User::factory()->create();
        $firstTenant = $this->createTenantFor($firstUser);
        $secondUser = User::factory()->create();
        $secondTenant = $this->createTenantFor($secondUser);

        foreach ([[$firstUser, $firstTenant], [$secondUser, $secondTenant]] as [$user, $tenant]) {
            $this->actingAs($user)
                ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
                ->post(route('admin.stores.store'), $this->validPayload('Same Store'))
                ->assertRedirect();
        }

        $this->assertEqualsCanonicalizing(
            ['same-store', 'same-store-2'],
            StorefrontPublication::query()->pluck('public_handle')->all(),
        );
        $this->assertSame(0, StorefrontPublication::query()
            ->where('status', StorefrontPublicationStatus::Published)->count());
    }

    public function test_admin_can_create_store(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, SystemRole::Admin);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.stores.store'), $this->validPayload('Admin Store'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('stores', [
            'tenant_id' => $tenant->getKey(),
            'name' => 'Admin Store',
        ]);
    }

    public function test_member_cannot_view_or_submit_store_creation(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, SystemRole::Member);

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.stores.create'))
            ->assertForbidden();

        $this->post(route('admin.stores.store'), $this->validPayload('Forbidden Store'))
            ->assertForbidden();

        $this->assertDatabaseMissing('stores', ['name' => 'Forbidden Store']);
    }

    public function test_frontend_tenant_id_is_ignored(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor(User::factory()->create());

        $this->actingAs($user)
            ->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])
            ->post(route('admin.stores.store'), [
                ...$this->validPayload('Scoped Store'),
                'tenant_id' => $tenantB->getKey(),
                'store_id' => (string) fake()->uuid(),
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('stores', [
            'tenant_id' => $tenantA->getKey(),
            'name' => 'Scoped Store',
        ]);
        $this->assertDatabaseMissing('stores', [
            'tenant_id' => $tenantB->getKey(),
            'name' => 'Scoped Store',
        ]);
    }

    public function test_slug_collisions_are_resolved_within_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->post(route('admin.stores.store'), $this->validPayload('Central Store'));
        $this->post(route('admin.stores.store'), $this->validPayload('Central Store'));

        $this->assertEqualsCanonicalizing(
            ['central-store', 'central-store-2'],
            $tenant->stores()->pluck('slug')->all(),
        );
    }

    public function test_same_slug_is_allowed_in_different_tenants(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);
        $this->post(route('admin.stores.store'), $this->validPayload('Shared Name'));

        session()->put(TenantContext::SESSION_KEY, $tenantB->getKey());
        session()->forget(StoreContext::SESSION_KEY);
        $this->post(route('admin.stores.store'), $this->validPayload('Shared Name'));

        $this->assertDatabaseHas('stores', ['tenant_id' => $tenantA->getKey(), 'slug' => 'shared-name']);
        $this->assertDatabaseHas('stores', ['tenant_id' => $tenantB->getKey(), 'slug' => 'shared-name']);
    }

    public function test_creating_additional_store_preserves_current_selection(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $currentStore = $this->createStore($tenant);

        $this->actingAs($user)
            ->withSession([
                TenantContext::SESSION_KEY => $tenant->getKey(),
                StoreContext::SESSION_KEY => $currentStore->getKey(),
            ])
            ->post(route('admin.stores.store'), $this->validPayload('Second Store'))
            ->assertSessionHas(StoreContext::SESSION_KEY, $currentStore->getKey());
    }

    /**
     * @return array{name: string, currency: string, timezone: string, locale: string}
     */
    private function validPayload(string $name): array
    {
        return [
            'name' => $name,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'locale' => 'en_US',
        ];
    }
}
