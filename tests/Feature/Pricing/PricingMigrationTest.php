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
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertInstanceOf(Migration::class, $inventoryMigration);
        $this->assertInstanceOf(Migration::class, $promotionMigration);
        $this->assertInstanceOf(Migration::class, $listingMigration);
        $this->assertInstanceOf(Migration::class, $cartMigration);
        $this->assertInstanceOf(Migration::class, $checkoutMigration);
        $this->assertInstanceOf(Migration::class, $orderMigration);
        $this->assertInstanceOf(Migration::class, $paymentMigration);
        $this->assertInstanceOf(Migration::class, $fulfillmentMigration);
        $this->assertInstanceOf(Migration::class, $partialCommitMigration);
        $auditMigration = require database_path('migrations/2026_09_08_000051_create_audit_entries.php');
        $notificationMigration = require database_path('migrations/2026_09_08_000052_create_admin_notifications.php');
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
        $firstId = PriceList::query()->where('tenant_id', $tenant->getKey())->sole()->getKey();
        $this->assertTrue(Schema::hasTable('price_lists'));
        $this->assertTrue(Schema::hasTable('variant_prices'));
        $this->assertDatabaseHas('price_lists', [
            'tenant_id' => $tenant->getKey(), 'code' => 'DEFAULT', 'currency' => 'USD',
            'status' => 'ACTIVE', 'is_default' => true,
        ]);

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
        $migration->down();
        $migration->up();
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
        $this->assertSame($firstId, PriceList::query()->where('tenant_id', $tenant->getKey())->sole()->getKey());
    }

    private function pricingPermissionCount(SystemRole $role): int
    {
        return Role::query()->system($role)->sole()->permissions()->where('module', 'pricing')->count();
    }
}
