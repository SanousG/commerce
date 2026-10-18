<?php

namespace Tests\Feature\Costing;

use App\Models\User;
use App\Modules\Costing\Application\InitializeInventoryCost;
use App\Modules\Costing\Domain\Enums\InventoryCostMovementType;
use App\Modules\Costing\Domain\Models\InventoryCostMovement;
use App\Modules\Costing\Domain\Models\InventoryValuation;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Procurement\Application\PostPurchaseReceipt;
use App\Modules\Procurement\Application\UpdateSupplierProduct;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Procurement\ProcurementFeatureTestCase;

class PurchaseReceiptCostingTest extends ProcurementFeatureTestCase
{
    public function test_first_and_second_receipts_use_moving_weighted_average(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $location = $this->createLocation($tenant);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $variant, ['unit_cost' => '100.0000', 'currency' => 'PEN']);

        $firstOrder = $this->orderedOrder($tenant, $supplier, $product, $actor, 10);
        $first = $this->receipt($tenant, $firstOrder, $location, $actor, 10, 'COST-100');
        app(PostPurchaseReceipt::class)->handle($tenant, $first);
        $this->assertSame('100.000000', InventoryValuation::query()->sole()->average_unit_cost);

        app(UpdateSupplierProduct::class)->handle(
            $tenant, $product, $product->supplier_sku, '120.0000', 'PEN', 1, null, SupplierProductStatus::Active,
        );
        $secondOrder = $this->orderedOrder($tenant, $supplier, $product->fresh(), $actor, 10);
        $second = $this->receipt($tenant, $secondOrder, $location, $actor, 10, 'COST-120');
        app(PostPurchaseReceipt::class)->handle($tenant, $second);

        $valuation = InventoryValuation::query()->sole();
        $level = InventoryLevel::query()->sole();
        $this->assertSame([20, '110.000000', 'PEN'], [$level->on_hand, $valuation->average_unit_cost, $valuation->currency]);
        $this->assertSame(
            ['1000.0000', '1200.0000'],
            InventoryCostMovement::query()->where('movement_type', InventoryCostMovementType::PurchaseReceipt)
                ->orderBy('occurred_at')->pluck('value_delta')->all(),
        );
    }

    public function test_currency_mismatch_rolls_back_stock_valuation_and_receipt(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $location = $this->createLocation($tenant);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $variant, ['unit_cost' => '10.0000', 'currency' => 'PEN']);
        $first = $this->receipt($tenant, $this->orderedOrder($tenant, $supplier, $product, $actor, 2), $location, $actor, 2, 'PEN');
        app(PostPurchaseReceipt::class)->handle($tenant, $first);

        app(UpdateSupplierProduct::class)->handle(
            $tenant, $product, $product->supplier_sku, '10.0000', 'USD', 1, null, SupplierProductStatus::Active,
        );
        $second = $this->receipt($tenant, $this->orderedOrder($tenant, $supplier, $product->fresh(), $actor, 1), $location, $actor, 1, 'USD');

        try {
            app(PostPurchaseReceipt::class)->handle($tenant, $second);
            $this->fail('A receipt with a mismatched valuation currency was posted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('INVENTORY_COST_CURRENCY_MISMATCH', $exception->errors()['inventory_cost'][0]);
        }

        $this->assertSame(2, InventoryLevel::query()->sole()->on_hand);
        $this->assertSame('10.000000', InventoryValuation::query()->sole()->average_unit_cost);
        $this->assertSame(PurchaseReceiptStatus::Draft, $second->fresh()->status);
        $this->assertSame(1, InventoryCostMovement::query()->count());
    }

    public function test_opening_cost_initializes_legacy_stock_once_without_changing_quantity(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $location = $this->createLocation($tenant);
        $this->stock($tenant, $location, $variant, 10, $actor);

        app(InitializeInventoryCost::class)->handle($tenant, $location, $variant, '80', 'PEN', $actor);
        $this->assertSame(10, InventoryLevel::query()->sole()->on_hand);
        $this->assertSame('80.000000', InventoryValuation::query()->sole()->average_unit_cost);
        $movement = InventoryCostMovement::query()->sole();
        $this->assertSame(InventoryCostMovementType::OpeningCost, $movement->movement_type);
        $this->assertSame('800.0000', $movement->value_delta);

        $this->expectException(ValidationException::class);
        app(InitializeInventoryCost::class)->handle($tenant, $location, $variant, '90', 'PEN', $actor);
    }
}
