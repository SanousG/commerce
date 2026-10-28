<?php

namespace Tests\Feature\Storefront;

use App\Modules\Storefront\Application\ProvisionStorefrontPublication;
use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class StorefrontProvisioningTest extends StorefrontFeatureTestCase
{
    public function test_reconciliation_prepares_missing_stores_and_is_idempotent(): void
    {
        $tenant = $this->createTenant();
        $first = $this->createStore($tenant, attributes: ['name' => 'Legacy Store']);
        $second = $this->createStore($tenant, attributes: ['name' => 'Legacy Store']);

        $this->artisan('storefront:reconcile', ['--tenant' => $tenant->getKey()])
            ->expectsOutputToContain('Reconciled 2')
            ->assertSuccessful();
        $this->artisan('storefront:reconcile', ['--tenant' => $tenant->getKey()])
            ->expectsOutputToContain('Reconciled 0')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ['legacy-store', 'legacy-store-2'],
            StorefrontPublication::query()->whereIn('store_id', [$first->getKey(), $second->getKey()])
                ->pluck('public_handle')->all(),
        );
        $this->assertSame(0, StorefrontPublication::query()
            ->where('status', StorefrontPublicationStatus::Published)->count());
    }

    public function test_reconciliation_preserves_existing_handle_and_publication_state(): void
    {
        $tenant = $this->createTenant();
        $publishedStore = $this->createStore($tenant, attributes: ['name' => 'Published']);
        $missingStore = $this->createStore($tenant, attributes: ['name' => 'Missing']);
        $existing = $this->publish($publishedStore, 'keep-this-handle');

        $this->artisan('storefront:reconcile')->assertSuccessful();

        $this->assertSame('keep-this-handle', $existing->refresh()->public_handle);
        $this->assertSame(StorefrontPublicationStatus::Published, $existing->status);
        $this->assertSame(StorefrontPublicationStatus::Unpublished, $missingStore
            ->storefrontPublication()->sole()->status);
    }

    public function test_handle_allocation_holds_a_cross_connection_transaction_lock(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant, attributes: ['name' => 'Concurrent Store']);
        config(['database.connections.pgsql_concurrent' => config('database.connections.pgsql')]);

        DB::beginTransaction();

        try {
            app(ProvisionStorefrontPublication::class)->handle($store);
            $result = DB::connection('pgsql_concurrent')->selectOne(
                "SELECT pg_try_advisory_xact_lock(hashtext('storefront-public-handle-allocation')) AS acquired",
            );

            $this->assertFalse((bool) $result->acquired);
        } finally {
            DB::rollBack();
            DB::disconnect('pgsql_concurrent');
        }
    }

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
