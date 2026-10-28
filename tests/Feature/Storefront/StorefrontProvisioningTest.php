<?php

namespace Tests\Feature\Storefront;

use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use Illuminate\Database\QueryException;

class StorefrontProvisioningTest extends StorefrontFeatureTestCase
{
    public function test_prepare_is_explicit_idempotent_and_does_not_publish(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);

        $arguments = [
            'tenant' => $tenant->getKey(),
            'store' => $store->getKey(),
            'publicHandle' => 'Mi Tienda',
        ];
        $this->artisan('storefront:prepare', $arguments)
            ->expectsOutputToContain("prepared as 'mi-tienda'")
            ->assertSuccessful();
        $this->artisan('storefront:prepare', $arguments)->assertSuccessful();

        $publication = StorefrontPublication::query()->sole();
        $this->assertSame(StorefrontPublicationStatus::Unpublished, $publication->status);
        $this->assertNull($publication->published_at);
        $this->assertSame(1, StorefrontPublication::query()->count());
        $this->get('/tienda/mi-tienda')->assertNotFound();
    }

    public function test_publish_and_unpublish_are_explicit_and_idempotent(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);
        $scope = ['tenant' => $tenant->getKey(), 'store' => $store->getKey()];
        $this->artisan('storefront:prepare', [...$scope, 'publicHandle' => 'operativa'])->assertSuccessful();

        $this->artisan('storefront:publish', $scope)->assertSuccessful();
        $publishedAt = StorefrontPublication::query()->sole()->published_at;
        $this->artisan('storefront:publish', $scope)->assertSuccessful();
        $this->assertTrue(StorefrontPublication::query()->sole()->published_at->equalTo($publishedAt));
        $this->get('/tienda/operativa')->assertOk();

        $this->artisan('storefront:unpublish', $scope)->assertSuccessful();
        $this->artisan('storefront:unpublish', $scope)->assertSuccessful();
        $publication = StorefrontPublication::query()->sole();
        $this->assertSame(StorefrontPublicationStatus::Unpublished, $publication->status);
        $this->assertNull($publication->published_at);
        $this->get('/tienda/operativa')->assertNotFound();
    }

    public function test_conflicting_handle_and_cross_tenant_store_are_rejected(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $storeA = $this->createStore($tenantA);
        $storeB = $this->createStore($tenantB);
        $this->artisan('storefront:prepare', [
            'tenant' => $tenantA->getKey(), 'store' => $storeA->getKey(), 'publicHandle' => 'global',
        ])
            ->assertSuccessful();

        $this->artisan('storefront:prepare', [
            'tenant' => $tenantB->getKey(), 'store' => $storeB->getKey(), 'publicHandle' => 'global',
        ])
            ->expectsOutputToContain('already assigned')
            ->assertFailed();
        $this->artisan('storefront:prepare', [
            'tenant' => $tenantA->getKey(), 'store' => $storeB->getKey(), 'publicHandle' => 'wrong-scope',
        ])
            ->expectsOutputToContain('combination was not found')
            ->assertFailed();
    }

    public function test_database_enforces_global_handle_uniqueness(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $storeA = $this->createStore($tenantA);
        $storeB = $this->createStore($tenantB);
        $this->publish($storeA, 'globally-unique');

        $this->expectException(QueryException::class);
        StorefrontPublication::query()->create([
            'tenant_id' => $tenantB->getKey(),
            'store_id' => $storeB->getKey(),
            'public_handle' => 'globally-unique',
            'status' => StorefrontPublicationStatus::Unpublished,
            'published_at' => null,
        ]);
    }

    public function test_new_store_has_no_publication_by_default(): void
    {
        $store = $this->createStore($this->createTenant());

        $this->assertNull($store->storefrontPublication()->first());
        $this->assertSame(0, StorefrontPublication::query()->count());
    }

    public function test_database_rejects_cross_tenant_store_publication(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $storeB = $this->createStore($tenantB);

        $this->expectException(QueryException::class);
        StorefrontPublication::query()->create([
            'tenant_id' => $tenantA->getKey(),
            'store_id' => $storeB->getKey(),
            'public_handle' => 'cross-tenant',
            'status' => StorefrontPublicationStatus::Unpublished,
            'published_at' => null,
        ]);
    }
}
