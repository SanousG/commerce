<?php

namespace Tests\Feature\Promotions;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Permission;
use App\Modules\Access\Domain\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromotionDatabaseTest extends PromotionsFeatureTestCase
{
    public function test_promotion_schema_round_trips(): void
    {
        $migration = require database_path('migrations/2026_09_08_000011_create_promotion_tables.php');
        $checkoutMigration = require database_path('migrations/2026_09_08_000019_create_checkout_tables.php');
        $orderMigration = require database_path('migrations/2026_09_08_000020_create_order_tables.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertInstanceOf(Migration::class, $checkoutMigration);
        $this->assertInstanceOf(Migration::class, $orderMigration);
        $orderMigration->down();
        $checkoutMigration->down();
        $migration->down();
        $this->assertFalse(Schema::hasTable('promotions'));
        $migration->up();
        $checkoutMigration->up();
        $orderMigration->up();
        $this->assertTrue(Schema::hasTable('promotions'));
        $this->assertTrue(Schema::hasTable('promotion_products'));
        $this->assertTrue(Schema::hasTable('promotion_categories'));
        $this->assertTrue(Schema::hasTable('promotion_variants'));
    }

    public function test_promotion_permissions_and_role_matrix_are_installed(): void
    {
        $keys = [
            PermissionKey::PromotionsView->value,
            PermissionKey::PromotionsCreate->value,
            PermissionKey::PromotionsUpdate->value,
            PermissionKey::PromotionsArchive->value,
        ];
        $this->assertEqualsCanonicalizing($keys, Permission::query()->where('module', 'promotions')->pluck('key')->all());
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $this->assertEqualsCanonicalizing($keys, Role::query()->system($role)->sole()->permissions()
                ->where('module', 'promotions')->pluck('key')->all());
        }
        $this->assertSame([PermissionKey::PromotionsView->value], Role::query()->system(SystemRole::Member)
            ->sole()->permissions()->where('module', 'promotions')->pluck('key')->all());
    }

    public function test_database_rejects_cross_tenant_target_pivots(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $promotion = $this->createPromotion($tenantA);
        $foreignProduct = $this->createVariant($tenantB)->product;

        $this->expectException(QueryException::class);
        DB::table('promotion_products')->insert([
            'tenant_id' => $tenantA->getKey(),
            'promotion_id' => $promotion->getKey(),
            'product_id' => $foreignProduct->getKey(),
        ]);
    }

    public function test_database_checks_invalid_discount_currency_dates_and_duplicate_targets(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $this->expectException(QueryException::class);
        DB::table('promotions')->insert([
            'id' => fake()->uuid(), 'tenant_id' => $tenant->getKey(), 'name' => 'Invalid',
            'status' => 'ACTIVE', 'discount_type' => 'PERCENTAGE', 'discount_value' => '101',
            'currency' => 'PEN', 'priority' => 0, 'scope' => 'ALL',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
