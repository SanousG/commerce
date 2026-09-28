<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Modules\Procurement\Application\CreateSupplierProduct;
use App\Modules\Procurement\Application\UpdateSupplierProduct;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class SupplierProductTest extends ProcurementFeatureTestCase
{
    public function test_variant_supports_multiple_suppliers_with_exact_costs_and_nullable_sku(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $first = app(CreateSupplierProduct::class)->handle(
            $tenant, $this->supplier($tenant), $variant, ' ', '123456789012345.6789', ' usd ', 6, 3, 14, true,
        );
        $second = app(CreateSupplierProduct::class)->handle(
            $tenant, $this->supplier($tenant), $variant, null, '0.0001', 'EUR', 1, null, 0, false,
        );

        $this->assertSame(['123456789012345.6789', 'USD', 6, 3, 14, null], [
            $first->unit_cost, $first->currency, $first->minimum_order_quantity,
            $first->order_multiple, $first->lead_time_days, $first->supplier_sku,
        ]);
        $this->assertSame('0.0001', $second->unit_cost);
        $this->assertSame(2, SupplierProduct::query()->where('product_variant_id', $variant->getKey())->count());
    }

    public function test_duplicate_supplier_variant_relation_is_rejected(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $supplier = $this->supplier($tenant);
        $variant = $this->createVariant($tenant);
        $this->supplierProduct($tenant, $supplier, $variant);

        $this->expectException(QueryException::class);
        $this->supplierProduct($tenant, $supplier, $variant, ['supplier_sku' => 'OTHER']);
    }

    public function test_currency_quantity_and_precision_rules_are_validated(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $supplier = $this->supplier($tenant);
        $variant = $this->createVariant($tenant);

        foreach ([
            ['1.00001', 'USD', 1, null],
            ['1.0000', 'US', 1, null],
            ['1.0000', 'USD', 0, null],
            ['1.0000', 'USD', 1, 0],
        ] as [$cost, $currency, $moq, $multiple]) {
            try {
                app(CreateSupplierProduct::class)->handle($tenant, $supplier, $variant, null, $cost, $currency, $moq, $multiple);
                $this->fail('Invalid supplier product values were accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_setting_a_new_preferred_supplier_product_clears_the_previous_one(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $first = $this->supplierProduct($tenant, $this->supplier($tenant), $variant, ['is_preferred' => true]);
        $second = $this->supplierProduct($tenant, $this->supplier($tenant), $variant);

        app(UpdateSupplierProduct::class)->handle(
            $tenant, $second, null, '9.5000', 'USD', 2, 2,
            SupplierProductStatus::Active, 5, true,
        );

        $this->assertFalse($first->fresh()->is_preferred);
        $this->assertTrue($second->fresh()->is_preferred);
    }

    public function test_cross_tenant_supplier_or_variant_cannot_be_related(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());

        foreach ([
            [$this->supplier($tenantB), $this->createVariant($tenantA)],
            [$this->supplier($tenantA), $this->createVariant($tenantB)],
        ] as [$supplier, $variant]) {
            try {
                $this->supplierProduct($tenantA, $supplier, $variant);
                $this->fail('A cross-tenant supplier product was created.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }
}
