<?php

namespace Tests\Feature\Pricing;

use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Permission;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class PricingMigrationTest extends PricingFeatureTestCase
{
    public function test_pricing_permissions_are_idempotent_and_have_expected_matrix(): void
    {
        $migration = require database_path('migrations/2026_09_08_000007_add_pricing_permissions.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $migration->up();

        $this->assertCount(4, Permission::query()->where('module', 'pricing')->get());
        $this->assertSame(4, $this->pricingPermissionCount(SystemRole::Owner));
        $this->assertSame(4, $this->pricingPermissionCount(SystemRole::Admin));
        $this->assertSame(1, $this->pricingPermissionCount(SystemRole::Member));
    }

    public function test_existing_tenants_receive_deterministic_default_list_and_schema_round_trips(): void
    {
        $migration = require database_path('migrations/2026_09_08_000008_create_pricing_tables.php');
        $inventoryMigration = require database_path('migrations/2026_09_08_000009_create_inventory_tables.php');
        $promotionMigration = require database_path('migrations/2026_09_08_000011_create_promotion_tables.php');
        $listingMigration = require database_path('migrations/2026_09_08_000013_create_store_listing_tables.php');
        $cartMigration = require database_path('migrations/2026_09_08_000017_create_cart_tables.php');
        $checkoutMigration = require database_path('migrations/2026_09_08_000019_create_checkout_tables.php');
        $orderMigration = require database_path('migrations/2026_09_08_000020_create_order_tables.php');
        $this->assertInstanceOf(Migration::class, $migration);
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
        $migration->down();
        $tenant = Tenant::create(['name' => 'Legacy', 'slug' => 'legacy', 'status' => TenantStatus::Active]);
        config()->set('commerce.default_currency', ' usd ');

        $migration->up();
        $inventoryMigration->up();
        $promotionMigration->up();
        $listingMigration->up();
        $cartMigration->up();
        $checkoutMigration->up();
        $orderMigration->up();
        $firstId = PriceList::query()->where('tenant_id', $tenant->getKey())->sole()->getKey();
        $this->assertTrue(Schema::hasTable('price_lists'));
        $this->assertTrue(Schema::hasTable('variant_prices'));
        $this->assertDatabaseHas('price_lists', [
            'tenant_id' => $tenant->getKey(), 'code' => 'DEFAULT', 'currency' => 'USD',
            'status' => 'ACTIVE', 'is_default' => true,
        ]);

        $orderMigration->down();
        $checkoutMigration->down();
        $cartMigration->down();
        $listingMigration->down();
        $promotionMigration->down();
        $inventoryMigration->down();
        $migration->down();
        $migration->up();
        $inventoryMigration->up();
        $promotionMigration->up();
        $listingMigration->up();
        $cartMigration->up();
        $checkoutMigration->up();
        $orderMigration->up();
        $this->assertSame($firstId, PriceList::query()->where('tenant_id', $tenant->getKey())->sole()->getKey());
    }

    private function pricingPermissionCount(SystemRole $role): int
    {
        return Role::query()->system($role)->sole()->permissions()->where('module', 'pricing')->count();
    }
}
