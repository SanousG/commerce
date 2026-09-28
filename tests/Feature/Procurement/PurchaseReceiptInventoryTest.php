<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Pricing\Domain\Models\VariantPrice;
use App\Modules\Procurement\Application\PostPurchaseReceipt;
use App\Modules\Procurement\Application\UpdateSupplierProduct;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use Illuminate\Validation\ValidationException;

class PurchaseReceiptInventoryTest extends ProcurementFeatureTestCase
{
    public function test_draft_has_no_inventory_effect_and_partial_then_full_posts_exact_movements(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $variant, ['unit_cost' => '7.2500']);
        $order = $this->orderedOrder($tenant, $supplier, $product, $actor, 5);
        $location = $this->createLocation($tenant);
        $level = InventoryLevel::query()->where('inventory_location_id', $location->getKey())
            ->where('product_variant_id', $variant->getKey())->first();
        $before = [$level?->on_hand ?? 0, $level?->reserved ?? 0];
        $first = $this->receipt($tenant, $order, $location, $actor, 2, 'PARTIAL');

        $this->assertSame($before, [
            InventoryLevel::query()->where('inventory_location_id', $location->getKey())->where('product_variant_id', $variant->getKey())->value('on_hand') ?? 0,
            InventoryLevel::query()->where('inventory_location_id', $location->getKey())->where('product_variant_id', $variant->getKey())->value('reserved') ?? 0,
        ]);
        app(PostPurchaseReceipt::class)->handle($tenant, $first);
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $order->fresh()->status);

        $second = $this->receipt($tenant, $order->fresh(), $location, $actor, 3, 'FULL');
        app(PostPurchaseReceipt::class)->handle($tenant, $second);
        $level = InventoryLevel::query()->where('inventory_location_id', $location->getKey())
            ->where('product_variant_id', $variant->getKey())->sole();
        $this->assertSame([$before[0] + 5, $before[1]], [$level->on_hand, $level->reserved]);
        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);

        $movements = InventoryMovement::query()->where('type', InventoryMovementType::PurchaseReceipt)->orderBy('quantity_delta')->get();
        $this->assertSame([2, 3], $movements->pluck('quantity_delta')->all());
        foreach ($movements as $movement) {
            $receiptItem = $movement->quantity_delta === 2 ? $first->items->sole() : $second->items->sole();
            $this->assertSame([$actor->getKey(), 'PURCHASE_RECEIPT_ITEM', $receiptItem->getKey(), $receiptItem->purchase_receipt_id], [
                $movement->actor_user_id, $movement->reference_type, $movement->reference_id, $movement->correlation_id,
            ]);
        }
    }

    public function test_second_post_is_idempotent(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $supplier = $this->supplier($tenant);
        $order = $this->orderedOrder($tenant, $supplier, $this->supplierProduct($tenant, $supplier, $variant), $actor, 2);
        $receipt = $this->receipt($tenant, $order, $this->createLocation($tenant), $actor, 2);

        app(PostPurchaseReceipt::class)->handle($tenant, $receipt);
        $count = InventoryMovement::query()->where('type', InventoryMovementType::PurchaseReceipt)->count();
        app(PostPurchaseReceipt::class)->handle($tenant, $receipt->fresh());

        $this->assertSame($count, InventoryMovement::query()->where('type', InventoryMovementType::PurchaseReceipt)->count());
        $this->assertSame(PurchaseReceiptStatus::Posted, $receipt->fresh()->status);
    }

    public function test_overreceipt_rolls_back_inventory_and_receipt_status(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $supplier = $this->supplier($tenant);
        $order = $this->orderedOrder($tenant, $supplier, $this->supplierProduct($tenant, $supplier, $variant), $actor, 5);
        $location = $this->createLocation($tenant);
        $first = $this->receipt($tenant, $order, $location, $actor, 3, 'FIRST');
        $over = $this->receipt($tenant, $order, $location, $actor, 3, 'OVER');
        app(PostPurchaseReceipt::class)->handle($tenant, $first);
        $before = InventoryLevel::query()->where('inventory_location_id', $location->getKey())
            ->where('product_variant_id', $variant->getKey())->sole()->on_hand;

        try {
            app(PostPurchaseReceipt::class)->handle($tenant, $over);
            $this->fail('An overreceipt was posted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertSame(PurchaseReceiptStatus::Draft, $over->fresh()->status);
        $this->assertSame($before, InventoryLevel::query()->where('inventory_location_id', $location->getKey())
            ->where('product_variant_id', $variant->getKey())->sole()->on_hand);
        $this->assertSame(1, InventoryMovement::query()->where('type', InventoryMovementType::PurchaseReceipt)->count());
    }

    public function test_receipts_can_target_multiple_locations_and_preserve_cost_snapshot_without_pricing_effects(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $variant, ['unit_cost' => '4.1234']);
        $order = $this->orderedOrder($tenant, $supplier, $product, $actor, 4);
        $locationA = $this->createLocation($tenant);
        $locationB = $this->createLocation($tenant);
        $receiptA = $this->receipt($tenant, $order, $locationA, $actor, 1, 'A');
        $receiptB = $this->receipt($tenant, $order, $locationB, $actor, 3, 'B');
        $priceCount = VariantPrice::query()->count();

        app(UpdateSupplierProduct::class)->handle(
            $tenant, $product, $product->supplier_sku, '99.0000', 'USD', 1, null, SupplierProductStatus::Active,
        );
        app(PostPurchaseReceipt::class)->handle($tenant, $receiptA);
        app(PostPurchaseReceipt::class)->handle($tenant, $receiptB);

        $this->assertSame(['4.1234', '4.1234'], [$receiptA->items->sole()->unit_cost, $receiptB->items->sole()->unit_cost]);
        $this->assertSame([1, 3], [
            InventoryLevel::query()->where('inventory_location_id', $locationA->getKey())->where('product_variant_id', $variant->getKey())->sole()->on_hand,
            InventoryLevel::query()->where('inventory_location_id', $locationB->getKey())->where('product_variant_id', $variant->getKey())->sole()->on_hand,
        ]);
        $this->assertSame($priceCount, VariantPrice::query()->count());
    }
}
