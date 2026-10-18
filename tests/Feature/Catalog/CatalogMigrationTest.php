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
        $paymentMigration = require database_path('migrations/2026_09_08_000022_create_payment_tables.php');
        $fulfillmentMigration = require database_path('migrations/2026_09_08_000024_create_fulfillment_tables.php');
        $partialCommitMigration = require database_path('migrations/2026_09_08_000025_extend_inventory_reservations_for_partial_commits.php');
        $returnMigration = require database_path('migrations/2026_09_08_000027_create_return_and_exchange_tables.php');
        $refundMigration = require database_path('migrations/2026_09_08_000028_create_refunds_and_link_ledger.php');
        $returnInventoryMigration = require database_path('migrations/2026_09_08_000029_add_return_inventory_support.php');
        $supplierMigration = require database_path('migrations/2026_09_08_000031_create_suppliers_and_supplier_products.php');
        $purchaseOrderMigration = require database_path('migrations/2026_09_08_000032_create_purchase_orders.php');
        $purchaseReceiptMigration = require database_path('migrations/2026_09_08_000033_create_purchase_receipts.php');
        $purchaseInventoryMigration = require database_path('migrations/2026_09_08_000034_add_purchase_receipt_inventory_support.php');
        $taxMigration = require database_path('migrations/2026_09_08_000036_create_tax_tables.php');
        $productTaxMigration = require database_path('migrations/2026_09_08_000037_add_tax_category_to_products.php');
        $fiscalConfigurationMigration = require database_path('migrations/2026_09_08_000038_create_fiscal_configuration_tables.php');
        $fiscalDocumentMigration = require database_path('migrations/2026_09_08_000039_create_fiscal_document_tables.php');
        $fiscalSubmissionMigration = require database_path('migrations/2026_09_08_000040_create_fiscal_submissions.php');
        $sunatSubmissionMigration = require database_path('migrations/2026_09_08_000044_extend_fiscal_submissions_for_sunat.php');
        $dailySummaryMigration = require database_path('migrations/2026_09_08_000045_create_fiscal_daily_summaries.php');
        $costingMigration = require database_path('migrations/2026_09_08_000048_create_inventory_costing_tables.php');
        $fulfillmentCostMigration = require database_path('migrations/2026_09_08_000049_add_cost_snapshot_to_fulfillment_items.php');
        $mediaMigration = require database_path('migrations/2026_09_08_000053_create_media_tables.php');
        $productMediaMigration = require database_path('migrations/2026_09_08_000054_create_product_media_tables.php');
        $storeSettingsMigration = require database_path('migrations/2026_09_08_000056_create_store_settings_table.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertInstanceOf(Migration::class, $pricingMigration);
        $this->assertInstanceOf(Migration::class, $inventoryMigration);
        $this->assertInstanceOf(Migration::class, $promotionMigration);
        $this->assertInstanceOf(Migration::class, $listingMigration);
        $this->assertInstanceOf(Migration::class, $cartMigration);
        $this->assertInstanceOf(Migration::class, $checkoutMigration);
        $this->assertInstanceOf(Migration::class, $orderMigration);
        $this->assertInstanceOf(Migration::class, $paymentMigration);
        $this->assertInstanceOf(Migration::class, $fulfillmentMigration);
        $this->assertInstanceOf(Migration::class, $partialCommitMigration);
        $this->assertInstanceOf(Migration::class, $storeSettingsMigration);

        $auditMigration = require database_path('migrations/2026_09_08_000051_create_audit_entries.php');
        $notificationMigration = require database_path('migrations/2026_09_08_000052_create_admin_notifications.php');
        $storeSettingsMigration->down();
        $productMediaMigration->down();
        $mediaMigration->down();
        $notificationMigration->down();
        $auditMigration->down();
        $fulfillmentCostMigration->down();
        $costingMigration->down();
        $dailySummaryMigration->down();
        $sunatSubmissionMigration->down();
        $fiscalSubmissionMigration->down();
        $fiscalDocumentMigration->down();
        $fiscalConfigurationMigration->down();
        $productTaxMigration->down();
        $taxMigration->down();
        $purchaseInventoryMigration->down();
        $purchaseReceiptMigration->down();
        $purchaseOrderMigration->down();
        $supplierMigration->down();
        $returnInventoryMigration->down();
        $refundMigration->down();
        $returnMigration->down();
        $partialCommitMigration->down();
        $fulfillmentMigration->down();
        $paymentMigration->down();
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
        $paymentMigration->up();
        $fulfillmentMigration->up();
        $partialCommitMigration->up();
        $returnMigration->up();
        $refundMigration->up();
        $returnInventoryMigration->up();
        $supplierMigration->up();
        $purchaseOrderMigration->up();
        $purchaseReceiptMigration->up();
        $purchaseInventoryMigration->up();
        $taxMigration->up();
        $productTaxMigration->up();
        $fiscalConfigurationMigration->up();
        $fiscalDocumentMigration->up();
        $fiscalSubmissionMigration->up();
        $sunatSubmissionMigration->up();
        $dailySummaryMigration->up();
        $costingMigration->up();
        $fulfillmentCostMigration->up();
        $mediaMigration->up();
        $productMediaMigration->up();
        $storeSettingsMigration->up();
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
