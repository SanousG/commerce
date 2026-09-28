<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProcurementDatabaseConstraintTest extends ProcurementFeatureTestCase
{
    public function test_supplier_status_and_code_constraints_are_enforced(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());

        $this->expectException(QueryException::class);
        DB::table('suppliers')->insert([
            'id' => fake()->uuid(), 'tenant_id' => $tenant->getKey(), 'code' => ' lower ',
            'name' => 'Supplier', 'status' => 'INVALID', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_supplier_product_tenant_coherence_is_enforced(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());

        $this->expectException(QueryException::class);
        SupplierProduct::query()->create([
            'tenant_id' => $tenantA->getKey(), 'supplier_id' => $this->supplier($tenantB)->getKey(),
            'product_variant_id' => $this->createVariant($tenantA)->getKey(), 'supplier_sku' => null,
            'status' => 'ACTIVE', 'unit_cost' => '1.0000', 'currency' => 'USD',
            'minimum_order_quantity' => 1, 'is_preferred' => false,
        ]);
    }

    public function test_supplier_product_currency_values_and_preferred_constraints_are_enforced(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $product = $this->supplierProduct($tenant, $this->supplier($tenant), $this->createVariant($tenant));

        $this->expectException(QueryException::class);
        DB::table('supplier_products')->where('id', $product->getKey())->update([
            'currency' => 'usd', 'minimum_order_quantity' => 0, 'is_preferred' => true, 'status' => 'INACTIVE',
        ]);
    }

    public function test_database_allows_only_one_active_preferred_supplier_per_variant(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $this->supplierProduct($tenant, $this->supplier($tenant), $variant, ['is_preferred' => true]);

        $this->expectException(QueryException::class);
        SupplierProduct::query()->create([
            'tenant_id' => $tenant->getKey(), 'supplier_id' => $this->supplier($tenant)->getKey(),
            'product_variant_id' => $variant->getKey(), 'supplier_sku' => null, 'status' => 'ACTIVE',
            'unit_cost' => '2.0000', 'currency' => 'USD', 'minimum_order_quantity' => 1,
            'is_preferred' => true,
        ]);
    }

    public function test_purchase_order_status_currency_totals_and_supplier_coherence_are_enforced(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $order = $this->draftOrder(
            $tenantA,
            $supplier = $this->supplier($tenantA),
            $this->supplierProduct($tenantA, $supplier, $this->createVariant($tenantA)),
        );

        $this->expectException(QueryException::class);
        DB::table('purchase_orders')->where('id', $order->getKey())->update([
            'supplier_id' => $this->supplier($tenantB)->getKey(), 'status' => 'INVALID',
            'currency' => 'usd', 'grand_total' => '999.0000',
        ]);
    }

    public function test_purchase_order_item_relationship_coherence_is_enforced(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $supplier = $this->supplier($tenant);
        $order = $this->draftOrder($tenant, $supplier, $this->supplierProduct($tenant, $supplier, $this->createVariant($tenant)));

        $this->expectException(QueryException::class);
        DB::table('purchase_order_items')->where('id', $order->items()->sole()->getKey())
            ->update(['variant_id' => $this->createVariant($tenant)->getKey()]);
    }

    public function test_purchase_receipt_relationship_and_status_constraints_are_enforced(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $supplier = $this->supplier($tenant);
        $order = $this->orderedOrder($tenant, $supplier, $this->supplierProduct($tenant, $supplier, $this->createVariant($tenant)), $actor);
        $receipt = $this->receipt($tenant, $order, $this->createLocation($tenant), $actor, 1);

        $this->expectException(QueryException::class);
        DB::table('purchase_receipts')->where('id', $receipt->getKey())->update([
            'status' => 'INVALID', 'supplier_id' => fake()->uuid(),
        ]);
    }
}
