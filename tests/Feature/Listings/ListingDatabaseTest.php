<?php

namespace Tests\Feature\Listings;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Permission;
use App\Modules\Access\Domain\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ListingDatabaseTest extends ListingsFeatureTestCase
{
    public function test_listing_permissions_have_the_expected_role_matrix(): void
    {
        $keys = [
            PermissionKey::ListingsView->value,
            PermissionKey::ListingsCreate->value,
            PermissionKey::ListingsUpdate->value,
            PermissionKey::ListingsPublish->value,
            PermissionKey::ListingsArchive->value,
        ];

        $this->assertEqualsCanonicalizing($keys, Permission::query()->where('module', 'listings')->pluck('key')->all());
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $this->assertEqualsCanonicalizing($keys, Role::query()->system($role)->sole()->permissions()
                ->where('module', 'listings')->pluck('key')->all());
        }
        $this->assertSame(
            [PermissionKey::ListingsView->value],
            Role::query()->system(SystemRole::Member)->sole()->permissions()->where('module', 'listings')->pluck('key')->all(),
        );
    }

    public function test_database_rejects_a_listing_whose_store_and_tenant_do_not_match(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $foreignTenant = $this->createTenantFor(User::factory()->create());

        $this->expectException(QueryException::class);
        DB::table('store_listings')->insert([
            'id' => fake()->uuid(),
            'tenant_id' => $tenant->getKey(),
            'store_id' => $this->createStore($foreignTenant)->getKey(),
            'product_id' => $this->createProduct($tenant)->getKey(),
            'status' => 'DRAFT',
            'storefront_slug' => 'invalid-store-tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_a_listing_variant_from_another_product(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $listing = $this->createListing($store, $this->createProduct($tenant));
        $otherVariant = $this->createProduct($tenant)->variants()->sole();

        $this->expectException(QueryException::class);
        DB::table('store_listing_variants')->insert([
            'id' => fake()->uuid(),
            'tenant_id' => $tenant->getKey(),
            'store_listing_id' => $listing->getKey(),
            'product_id' => $listing->product_id,
            'product_variant_id' => $otherVariant->getKey(),
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_a_cross_tenant_store_default_price_list(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $foreignList = $this->createTenantFor(User::factory()->create())->defaultPriceList()->sole();

        $this->expectException(QueryException::class);
        DB::table('stores')->where('id', $store->getKey())->update([
            'default_price_list_id' => $foreignList->getKey(),
        ]);
    }

    public function test_database_enforces_listing_product_and_slug_uniqueness_per_store(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $listing = $this->createListing($store, $product, ['storefront_slug' => 'unique-slug']);

        $this->expectException(QueryException::class);
        DB::table('store_listings')->insert([
            'id' => fake()->uuid(),
            'tenant_id' => $tenant->getKey(),
            'store_id' => $store->getKey(),
            'product_id' => $listing->product_id,
            'status' => 'DRAFT',
            'storefront_slug' => 'another-slug',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
