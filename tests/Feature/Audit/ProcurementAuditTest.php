<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Models\AuditEntry;
use App\Modules\Inventory\Domain\Enums\InventoryMovementType;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Procurement\Application\ApprovePurchaseOrder;
use App\Modules\Procurement\Application\PostPurchaseReceipt;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use Tests\Feature\Procurement\ProcurementFeatureTestCase;

class ProcurementAuditTest extends ProcurementFeatureTestCase
{
    public function test_purchase_order_approval_records_business_snapshot_once(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $this->createVariant($tenant), ['unit_cost' => '12.3456']);
        $order = $this->draftOrder($tenant, $supplier, $product, 5, $actor);

        $approved = app(ApprovePurchaseOrder::class)->handle($tenant, $order, $actor);

        $audit = AuditEntry::query()->where('action', AuditAction::PurchaseOrderApproved)->sole();
        $this->assertSame($order->getKey(), $audit->subject_id);
        $this->assertSame($order->number, $audit->subject_label);
        $this->assertEquals([
            'quantity' => 5,
            'grand_total' => '61.7280',
            'currency' => 'USD',
        ], $audit->metadata);
        $this->assertSame(PurchaseOrderStatus::Approved, $approved->status);
        $this->assertSame($actor->getKey(), $approved->approved_by_user_id);
    }

    public function test_purchase_receipt_double_post_has_one_audit_and_one_inventory_effect(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $variant = $this->createVariant($tenant);
        $supplier = $this->supplier($tenant);
        $product = $this->supplierProduct($tenant, $supplier, $variant, ['unit_cost' => '7.2500']);
        $order = $this->orderedOrder($tenant, $supplier, $product, $actor, 2);
        $location = $this->createLocation($tenant);
        $receipt = $this->receipt($tenant, $order, $location, $actor, 2, 'AUDIT-RECEIPT');

        $action = app(PostPurchaseReceipt::class);
        $action->handle($tenant, $receipt, $actor);
        $action->handle($tenant, $receipt->fresh(), $actor);

        $audit = AuditEntry::query()->where('action', AuditAction::PurchaseReceiptPosted)->sole();
        $this->assertEquals([
            'purchase_order_number' => $order->number,
            'quantity' => 2,
            'total_cost' => '14.5000',
            'currency' => 'USD',
        ], $audit->metadata);
        $this->assertSame('purchase_receipt.posted:'.$receipt->getKey(), $audit->idempotency_key);
        $this->assertSame(1, InventoryMovement::query()->where('type', InventoryMovementType::PurchaseReceipt)->count());
        $this->assertSame(2, InventoryLevel::query()->where('inventory_location_id', $location->getKey())
            ->where('product_variant_id', $variant->getKey())->sole()->on_hand);
        $this->assertSame(PurchaseReceiptStatus::Posted, $receipt->fresh()->status);
    }
}
