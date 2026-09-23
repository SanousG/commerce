<?php

namespace Tests\Feature\Catalog;

use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Permission;
use App\Modules\Access\Domain\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class CatalogMigrationTest extends CatalogFeatureTestCase
{
    public function test_category_permissions_are_idempotent_and_have_expected_matrix(): void
    {
        $migration = require database_path('migrations/2026_09_08_000005_add_category_permissions.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $migration->up();

        $categoryPermissionIds = Permission::query()->where('module', 'categories')->pluck('id');
        $this->assertCount(4, $categoryPermissionIds);
        $this->assertSame(4, $this->categoryPermissionCount(SystemRole::Owner));
        $this->assertSame(4, $this->categoryPermissionCount(SystemRole::Admin));
        $this->assertSame(1, $this->categoryPermissionCount(SystemRole::Member));
    }

    public function test_catalog_schema_round_trip_cleans_up_postgresql_function(): void
    {
        $migration = require database_path('migrations/2026_09_08_000006_create_catalog_tables.php');
        $pricingMigration = require database_path('migrations/2026_09_08_000008_create_pricing_tables.php');
        $inventoryMigration = require database_path('migrations/2026_09_08_000009_create_inventory_tables.php');
        $promotionMigration = require database_path('migrations/2026_09_08_000011_create_promotion_tables.php');
        $listingMigration = require database_path('migrations/2026_09_08_000013_create_store_listing_tables.php');
        $cartMigration = require database_path('migrations/2026_09_08_000017_create_cart_tables.php');
        $checkoutMigration = require database_path('migrations/2026_09_08_000019_create_checkout_tables.php');
        $orderMigration = require database_path('migrations/2026_09_08_000020_create_order_tables.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertInstanceOf(Migration::class, $pricingMigration);
        $this->assertInstanceOf(Migration::class, $inventoryMigration);
        $this->assertInstanceOf(Migration::class, $promotionMigration);
        $this->assertInstanceOf(Migration::class, $listingMigration);
        $this->assertInstanceOf(Migration::class, $cartMigration);
        $this->assertInstanceOf(Migration::class, $checkoutMigration);
        $this->assertInstanceOf(Migration::class, $orderMigration);

        $orderMigration->down();
        $checkoutMigration->down();
        $cartMigration->down();
        $listingMigration->down();
        $promotionMigration->down();
        $inventoryMigration->down();
        $pricingMigration->down();
        $migration->down();
        $this->assertFalse(Schema::hasTable('products'));
        $this->assertFalse(Schema::hasTable('product_variants'));
        $this->assertFalse(Schema::hasTable('categories'));

        $migration->up();
        $pricingMigration->up();
        $inventoryMigration->up();
        $promotionMigration->up();
        $listingMigration->up();
        $cartMigration->up();
        $checkoutMigration->up();
        $orderMigration->up();
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('product_variants'));
        $this->assertTrue(Schema::hasTable('product_options'));
        $this->assertTrue(Schema::hasTable('product_option_values'));
        $this->assertTrue(Schema::hasTable('product_variant_option_values'));
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('category_product'));
    }

    private function categoryPermissionCount(SystemRole $role): int
    {
        return Role::query()->system($role)->sole()->permissions()
            ->where('module', 'categories')
            ->count();
    }
}
