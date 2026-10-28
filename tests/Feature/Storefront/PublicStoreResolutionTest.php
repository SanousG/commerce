<?php

namespace Tests\Feature\Storefront;

use App\Models\User;
use App\Modules\Storefront\Application\PublicStoreContext;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use Inertia\Testing\AssertableInertia as Assert;

class PublicStoreResolutionTest extends StorefrontFeatureTestCase
{
    public function test_it_resolves_a_published_active_store(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant, attributes: [
            'name' => 'Internal Store Name',
            'display_name' => 'Tienda Pública',
            'short_description' => 'Identidad comercial autorizada.',
            'currency' => 'USD',
            'locale' => 'en_US',
        ]);
        $this->publish($store, 'tienda-publica');

        $this->get('/tienda/tienda-publica')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Home')
                ->where('store.publicHandle', 'tienda-publica')
                ->where('store.name', 'Tienda Pública')
                ->where('store.description', 'Identidad comercial autorizada.')
                ->where('store.currency', 'USD')
                ->where('store.locale', 'en')
                ->where('localization.locale', 'en'));

        $this->assertFalse(app(PublicStoreContext::class)->hasStore());
    }

    public function test_unknown_unpublished_and_inactive_contexts_return_not_found(): void
    {
        $this->get('/tienda/no-existe')->assertNotFound();

        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);
        StorefrontPublication::query()->create([
            'tenant_id' => $tenant->getKey(),
            'store_id' => $store->getKey(),
            'public_handle' => 'sin-publicar',
            'status' => StorefrontPublicationStatus::Unpublished,
            'published_at' => null,
        ]);
        $this->get('/tienda/sin-publicar')->assertNotFound();

        $inactiveStore = $this->createStore($tenant, StoreStatus::Inactive);
        $this->publish($inactiveStore, 'store-inactiva');
        $this->get('/tienda/store-inactiva')->assertNotFound();

        $inactiveTenant = $this->createTenant(TenantStatus::Suspended);
        $tenantStore = $this->createStore($inactiveTenant);
        $this->publish($tenantStore, 'tenant-inactivo');
        $this->get('/tienda/tenant-inactivo')->assertNotFound();
    }

    public function test_stores_and_tenants_are_isolated_by_the_resolved_publication(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $storeA1 = $this->createStore($tenantA, attributes: ['display_name' => 'Store A1']);
        $storeA2 = $this->createStore($tenantA, attributes: ['display_name' => 'Store A2']);
        $storeB = $this->createStore($tenantB, attributes: ['display_name' => 'Store B']);
        $this->publish($storeA1, 'store-a1');
        $this->publish($storeA2, 'store-a2');
        $this->publish($storeB, 'store-b');

        $this->get('/tienda/store-a1?tenant_id='.$tenantB->getKey().'&store_id='.$storeB->getKey())
            ->assertInertia(fn (Assert $page) => $page->where('store.name', 'Store A1'));
        $this->get('/tienda/store-a2')
            ->assertInertia(fn (Assert $page) => $page->where('store.name', 'Store A2'));
        $this->get('/tienda/store-b')
            ->assertInertia(fn (Assert $page) => $page->where('store.name', 'Store B'));
    }

    public function test_public_resolution_does_not_use_or_mutate_admin_session_context(): void
    {
        $adminTenant = $this->createTenant();
        $adminStore = $this->createStore($adminTenant);
        $publicTenant = $this->createTenant();
        $publicStore = $this->createStore($publicTenant, attributes: ['display_name' => 'Public Target']);
        $this->publish($publicStore, 'public-target');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                TenantContext::SESSION_KEY => $adminTenant->getKey(),
                StoreContext::SESSION_KEY => $adminStore->getKey(),
            ])
            ->get('/tienda/public-target')
            ->assertOk()
            ->assertSessionHas(TenantContext::SESSION_KEY, $adminTenant->getKey())
            ->assertSessionHas(StoreContext::SESSION_KEY, $adminStore->getKey())
            ->assertInertia(fn (Assert $page) => $page
                ->where('store.name', 'Public Target')
                ->missing('auth')
                ->missing('currentTenant')
                ->missing('currentStore')
                ->missing('currentTenantMembership')
                ->missing('unreadNotificationsCount'));
    }

    public function test_public_props_do_not_expose_internal_or_contact_data(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant, attributes: [
            'display_name' => 'Safe Store',
            'contact_email' => 'private@example.test',
            'contact_phone' => '+51999999999',
        ]);
        $this->publish($store, 'safe-store');

        $this->get('/tienda/safe-store')->assertInertia(fn (Assert $page) => $page
            ->missing('store.id')
            ->missing('store.tenant_id')
            ->missing('store.store_id')
            ->missing('store.contact_email')
            ->missing('store.contact_phone')
            ->missing('store.logo_media_asset_id')
            ->missing('store.favicon_media_asset_id'));
    }
}
