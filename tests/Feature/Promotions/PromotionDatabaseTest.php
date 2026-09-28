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
        $paymentMigration = require database_path('migrations/2026_09_08_000022_create_payment_tables.php');
        $fulfillmentMigration = require database_path('migrations/2026_09_08_000024_create_fulfillment_tables.php');
        $partialCommitMigration = require database_path('migrations/2026_09_08_000025_extend_inventory_reservations_for_partial_commits.php');
        $returnMigration = require database_path('migrations/2026_09_08_000027_create_return_and_exchange_tables.php');
        $refundMigration = require database_path('migrations/2026_09_08_000028_create_refunds_and_link_ledger.php');
        $returnInventoryMigration = require database_path('migrations/2026_09_08_000029_add_return_inventory_support.php');
        $taxMigration = require database_path('migrations/2026_09_08_000036_create_tax_tables.php');
        $productTaxMigration = require database_path('migrations/2026_09_08_000037_add_tax_category_to_products.php');
        $fiscalConfigurationMigration = require database_path('migrations/2026_09_08_000038_create_fiscal_configuration_tables.php');
        $fiscalDocumentMigration = require database_path('migrations/2026_09_08_000039_create_fiscal_document_tables.php');
        $fiscalSubmissionMigration = require database_path('migrations/2026_09_08_000040_create_fiscal_submissions.php');
        $sunatSubmissionMigration = require database_path('migrations/2026_09_08_000044_extend_fiscal_submissions_for_sunat.php');
        $dailySummaryMigration = require database_path('migrations/2026_09_08_000045_create_fiscal_daily_summaries.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertInstanceOf(Migration::class, $checkoutMigration);
        $this->assertInstanceOf(Migration::class, $orderMigration);
        $this->assertInstanceOf(Migration::class, $paymentMigration);
        $this->assertInstanceOf(Migration::class, $fulfillmentMigration);
        $this->assertInstanceOf(Migration::class, $partialCommitMigration);
        $dailySummaryMigration->down();
        $sunatSubmissionMigration->down();
        $fiscalSubmissionMigration->down();
        $fiscalDocumentMigration->down();
        $fiscalConfigurationMigration->down();
        $productTaxMigration->down();
        $taxMigration->down();
        $returnInventoryMigration->down();
        $refundMigration->down();
        $returnMigration->down();
        $partialCommitMigration->down();
        $fulfillmentMigration->down();
        $paymentMigration->down();
        $orderMigration->down();
        $checkoutMigration->down();
        $migration->down();
        $this->assertFalse(Schema::hasTable('promotions'));
        $migration->up();
        $checkoutMigration->up();
        $orderMigration->up();
        $paymentMigration->up();
        $fulfillmentMigration->up();
        $partialCommitMigration->up();
        $returnMigration->up();
        $refundMigration->up();
        $returnInventoryMigration->up();
        $taxMigration->up();
        $productTaxMigration->up();
        $fiscalConfigurationMigration->up();
        $fiscalDocumentMigration->up();
        $fiscalSubmissionMigration->up();
        $sunatSubmissionMigration->up();
        $dailySummaryMigration->up();
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
