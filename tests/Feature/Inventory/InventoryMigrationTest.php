<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Pricing\Application\CreateDefaultPriceList;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InventoryMigrationTest extends InventoryFeatureTestCase
{
    public function test_existing_tenants_receive_deterministic_default_and_schema_round_trips(): void
    {
        $migration = require database_path('migrations/2026_09_08_000009_create_inventory_tables.php');
        $fulfillmentMigration = require database_path('migrations/2026_09_08_000024_create_fulfillment_tables.php');
        $partialCommitMigration = require database_path('migrations/2026_09_08_000025_extend_inventory_reservations_for_partial_commits.php');
        $returnMigration = require database_path('migrations/2026_09_08_000027_create_return_and_exchange_tables.php');
        $refundMigration = require database_path('migrations/2026_09_08_000028_create_refunds_and_link_ledger.php');
        $returnInventoryMigration = require database_path('migrations/2026_09_08_000029_add_return_inventory_support.php');
        $purchaseReceiptMigration = require database_path('migrations/2026_09_08_000033_create_purchase_receipts.php');
        $purchaseInventoryMigration = require database_path('migrations/2026_09_08_000034_add_purchase_receipt_inventory_support.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertInstanceOf(Migration::class, $fulfillmentMigration);
        $this->assertInstanceOf(Migration::class, $partialCommitMigration);
        $purchaseInventoryMigration->down();
        $purchaseReceiptMigration->down();
        $returnInventoryMigration->down();
        $refundMigration->down();
        $returnMigration->down();
        $partialCommitMigration->down();
        $fulfillmentMigration->down();
        $migration->down();
        $tenant = Tenant::create(['name' => 'Legacy', 'slug' => 'legacy-inventory', 'status' => TenantStatus::Active]);
        app(CreateDefaultPriceList::class)->handle($tenant);
        config()->set('commerce.default_inventory_location_name', ' Legacy warehouse ');
        config()->set('commerce.default_inventory_location_code', ' legacy_01 ');

        $migration->up();
        $fulfillmentMigration->up();
        $partialCommitMigration->up();
        $returnMigration->up();
        $refundMigration->up();
        $returnInventoryMigration->up();
        $purchaseReceiptMigration->up();
        $purchaseInventoryMigration->up();
        $firstId = InventoryLocation::query()->where('tenant_id', $tenant->getKey())->sole()->getKey();
        $this->assertTrue(Schema::hasTable('inventory_locations'));
        $this->assertTrue(Schema::hasTable('inventory_levels'));
        $this->assertTrue(Schema::hasTable('inventory_transfers'));
        $this->assertTrue(Schema::hasTable('inventory_transfer_lines'));
        $this->assertTrue(Schema::hasTable('inventory_reservations'));
        $this->assertTrue(Schema::hasTable('inventory_movements'));
        $this->assertDatabaseHas('inventory_locations', [
            'tenant_id' => $tenant->getKey(), 'name' => 'Legacy warehouse', 'code' => 'LEGACY_01',
            'status' => 'ACTIVE', 'is_default' => true,
        ]);

        $purchaseInventoryMigration->down();
        $purchaseReceiptMigration->down();
        $returnInventoryMigration->down();
        $refundMigration->down();
        $returnMigration->down();
        $partialCommitMigration->down();
        $fulfillmentMigration->down();
        $migration->down();
        $migration->up();
        $fulfillmentMigration->up();
        $partialCommitMigration->up();
        $returnMigration->up();
        $refundMigration->up();
        $returnInventoryMigration->up();
        $purchaseReceiptMigration->up();
        $purchaseInventoryMigration->up();
        $this->assertSame($firstId, InventoryLocation::query()->where('tenant_id', $tenant->getKey())->sole()->getKey());
    }

    public function test_database_rejects_cross_tenant_level_references(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $variantB = $this->createVariant($tenantB);

        $this->expectException(QueryException::class);
        DB::table('inventory_levels')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantA->getKey(),
            'inventory_location_id' => $tenantA->defaultInventoryLocation()->sole()->getKey(),
            'product_variant_id' => $variantB->getKey(),
            'on_hand' => 1,
            'reserved' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_invalid_movement_before_after_equation(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);

        $this->expectException(QueryException::class);
        DB::table('inventory_movements')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'inventory_location_id' => $tenant->defaultInventoryLocation()->sole()->getKey(),
            'product_variant_id' => $variant->getKey(),
            'type' => 'ADJUSTMENT_IN',
            'quantity_delta' => 2,
            'quantity_before' => 3,
            'quantity_after' => 4,
            'correlation_id' => (string) Str::uuid(),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_database_rejects_partial_reservation_reference(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);

        $this->expectException(QueryException::class);
        DB::table('inventory_reservations')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'inventory_location_id' => $tenant->defaultInventoryLocation()->sole()->getKey(),
            'product_variant_id' => $variant->getKey(),
            'reference_type' => 'order',
            'reference_id' => null,
            'quantity' => 1,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_same_location_transfer(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $location = $tenant->defaultInventoryLocation()->sole();

        $this->expectException(QueryException::class);
        DB::table('inventory_transfers')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'source_location_id' => $location->getKey(),
            'destination_location_id' => $location->getKey(),
            'status' => 'COMPLETED',
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_nonpositive_transfer_line_quantity(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $source = $tenant->defaultInventoryLocation()->sole();
        $destination = $this->createLocation($tenant);
        $variant = $this->createVariant($tenant);
        $transferId = (string) Str::uuid();
        DB::table('inventory_transfers')->insert([
            'id' => $transferId,
            'tenant_id' => $tenant->getKey(),
            'source_location_id' => $source->getKey(),
            'destination_location_id' => $destination->getKey(),
            'status' => 'COMPLETED',
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        DB::table('inventory_transfer_lines')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->getKey(),
            'inventory_transfer_id' => $transferId,
            'product_variant_id' => $variant->getKey(),
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
