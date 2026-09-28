<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Modules\Procurement\Application\AddPurchaseOrderItem;
use App\Modules\Procurement\Application\ApprovePurchaseOrder;
use App\Modules\Procurement\Application\CancelPurchaseOrder;
use App\Modules\Procurement\Application\CreatePurchaseOrder;
use App\Modules\Procurement\Application\MarkPurchaseOrderOrdered;
use App\Modules\Procurement\Application\PostPurchaseReceipt;
use App\Modules\Procurement\Application\UpdatePurchaseOrder;
use App\Modules\Procurement\Application\UpdatePurchaseOrderItem;
use App\Modules\Procurement\Application\UpdateSupplierProduct;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use Illuminate\Validation\ValidationException;

class PurchaseOrderLifecycleTest extends ProcurementFeatureTestCase
{
    public function test_drafts_use_independent_per_tenant_sequences(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $supplierA = $this->supplier($tenantA);
        $supplierB = $this->supplier($tenantB);

        $numbers = [
            app(CreatePurchaseOrder::class)->handle($tenantA, $supplierA, 'usd')->number,
            app(CreatePurchaseOrder::class)->handle($tenantA, $supplierA, 'USD')->number,
            app(CreatePurchaseOrder::class)->handle($tenantB, $supplierB, 'USD')->number,
        ];

        $this->assertSame(['PO-000001', 'PO-000002', 'PO-000001'], $numbers);
    }

    public function test_line_snapshots_and_totals_remain_stable_after_supplier_cost_changes(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $supplier = $this->supplier($tenant);
        $variant = $this->createVariant($tenant, 'SNAPSHOT-SKU', 'Snapshot Product');
        $product = $this->supplierProduct($tenant, $supplier, $variant, [
            'supplier_sku' => 'SUP-SNAPSHOT', 'unit_cost' => '10.1250',
        ]);
        $order = $this->draftOrder($tenant, $supplier, $product, 4);
        $item = $order->items->sole();

        app(UpdateSupplierProduct::class)->handle(
            $tenant, $product, 'CHANGED', '99.9999', 'USD', 1, null, SupplierProductStatus::Active,
        );

        $this->assertSame(['Snapshot Product', 'SNAPSHOT-SKU', 'SUP-SNAPSHOT', '10.1250', '40.5000'], [
            $item->fresh()->product_name, $item->fresh()->sku, $item->fresh()->supplier_sku,
            $item->fresh()->unit_cost, $item->fresh()->line_total,
        ]);
        $this->assertSame(['40.5000', '40.5000'], [$order->fresh()->subtotal, $order->fresh()->grand_total]);
    }

    public function test_moq_multiple_draft_updates_and_totals_are_enforced(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $this->createVariant($tenant), [
            'unit_cost' => '2.5000', 'minimum_order_quantity' => 6, 'order_multiple' => 3,
        ]);
        $order = app(CreatePurchaseOrder::class)->handle($tenant, $supplier, 'USD');

        foreach ([3, 7] as $quantity) {
            try {
                app(AddPurchaseOrderItem::class)->handle($tenant, $order, $product, $quantity);
                $this->fail('An invalid order quantity was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('quantity', $exception->errors());
            }
        }

        $item = app(AddPurchaseOrderItem::class)->handle($tenant, $order, $product, 6);
        app(UpdatePurchaseOrderItem::class)->handle($tenant, $order, $item, 9, '3.0000', 'Updated line');
        $updated = app(UpdatePurchaseOrder::class)->handle($tenant, $order, notes: 'Draft updated');
        $this->assertSame(['27.0000', '0.0000', '0.0000', '0.0000', '27.0000'], [
            $updated->subtotal, $updated->discount_total, $updated->shipping_total, $updated->tax_total, $updated->grand_total,
        ]);
    }

    public function test_valid_draft_with_items_can_be_approved(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $this->createVariant($tenant));
        $order = $this->draftOrder($tenant, $supplier, $product, actor: $actor);

        $approved = app(ApprovePurchaseOrder::class)->handle($tenant, $order, $actor);
        $this->assertSame([PurchaseOrderStatus::Approved, $actor->getKey()], [$approved->status, $approved->approved_by_user_id]);
    }

    public function test_mark_ordered_records_time_and_blocks_commercial_edits(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $this->createVariant($tenant));
        $order = $this->draftOrder($tenant, $supplier, $product, actor: $actor);
        $order->update(['status' => PurchaseOrderStatus::Approved, 'approved_by_user_id' => $actor->getKey()]);

        $ordered = app(MarkPurchaseOrderOrdered::class)->handle($tenant, $order->fresh());
        $this->assertSame(PurchaseOrderStatus::Ordered, $ordered->status);
        $this->assertNotNull($ordered->ordered_at);

        foreach ([
            fn () => app(UpdatePurchaseOrder::class)->handle($tenant, $ordered, notes: 'blocked'),
            fn () => app(UpdatePurchaseOrderItem::class)->handle($tenant, $ordered, $ordered->items()->sole(), 5),
            fn () => app(AddPurchaseOrderItem::class)->handle($tenant, $ordered, $product, 5),
        ] as $operation) {
            try {
                $operation();
                $this->fail('A non-draft purchase order was edited.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_order_can_cancel_without_receipts_but_not_after_posted_receipt(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $this->createVariant($tenant));
        $cancelled = app(CancelPurchaseOrder::class)->handle($tenant, $this->orderedOrder($tenant, $supplier, $product, $actor));
        $this->assertSame(PurchaseOrderStatus::Cancelled, $cancelled->status);

        $otherProduct = $this->supplierProduct($tenant, $supplier, $this->createVariant($tenant));
        $order = $this->orderedOrder($tenant, $supplier, $otherProduct, $actor);
        $receipt = $this->receipt($tenant, $order, $this->createLocation($tenant), $actor, 1);
        app(PostPurchaseReceipt::class)->handle($tenant, $receipt);

        $this->expectException(ValidationException::class);
        app(CancelPurchaseOrder::class)->handle($tenant, $order->fresh());
    }
}
