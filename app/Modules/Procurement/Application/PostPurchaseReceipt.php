<?php

namespace App\Modules\Procurement\Application;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Inventory\Application\ReceiveInventory;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Notifications\Application\OperationalNotificationPublisher;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Procurement\Domain\Models\PurchaseReceiptItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostPurchaseReceipt
{
    public function __construct(
        private readonly ReceiveInventory $inventory,
        private readonly PurchaseOrderReceivingStatusUpdater $statusUpdater,
        private readonly AuditRecorder $audit,
        private readonly OperationalNotificationPublisher $notifications,
    ) {}

    public function handle(Tenant $tenant, PurchaseReceipt $receipt, ?User $actor = null): PurchaseReceipt
    {
        return DB::transaction(function () use ($tenant, $receipt, $actor): PurchaseReceipt {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())
                ->lockForUpdate()->findOrFail($receipt->purchase_order_id);
            $receipt = PurchaseReceipt::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($receipt->getKey());
            if ($receipt->status === PurchaseReceiptStatus::Posted) {
                return $receipt->load('items');
            }
            if ($receipt->status !== PurchaseReceiptStatus::Draft) {
                throw ValidationException::withMessages(['purchase_receipt' => 'Only a draft receipt can be posted.']);
            }
            if ($receipt->purchase_order_id !== $order->getKey() || $receipt->supplier_id !== $order->supplier_id) {
                throw ValidationException::withMessages(['purchase_receipt' => 'The receipt does not belong to this purchase order and supplier.']);
            }
            if (! in_array($order->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw ValidationException::withMessages(['purchase_order' => 'The purchase order cannot receive inventory in its current state.']);
            }
            $receiptItems = PurchaseReceiptItem::query()->where('tenant_id', $tenant->getKey())
                ->where('purchase_receipt_id', $receipt->getKey())->orderBy('purchase_order_item_id')->lockForUpdate()->get();
            if ($receiptItems->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'A purchase receipt requires at least one item.']);
            }
            $orderItems = PurchaseOrderItem::query()->where('tenant_id', $tenant->getKey())->where('purchase_order_id', $order->getKey())
                ->whereIn('id', $receiptItems->pluck('purchase_order_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($orderItems->count() !== $receiptItems->count()) {
                throw ValidationException::withMessages(['items' => 'A receipt item is outside this purchase order or tenant.']);
            }
            $posted = DB::table('purchase_receipt_items as pri')->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                ->where('pri.tenant_id', $tenant->getKey())->where('pri.purchase_order_id', $order->getKey())
                ->where('pr.status', PurchaseReceiptStatus::Posted->value)->whereIn('pri.purchase_order_item_id', $orderItems->keys())
                ->groupBy('pri.purchase_order_item_id')->selectRaw('pri.purchase_order_item_id, SUM(pri.quantity) AS quantity')->get()->keyBy('purchase_order_item_id');
            foreach ($receiptItems as $item) {
                $orderItem = $orderItems[$item->purchase_order_item_id];
                $alreadyReceived = (int) ($posted->get($item->purchase_order_item_id)?->quantity ?? 0);
                if ($item->variant_id !== $orderItem->variant_id || $item->unit_cost !== $orderItem->unit_cost
                    || $item->quantity > $orderItem->quantity_ordered - $alreadyReceived) {
                    throw ValidationException::withMessages(['quantity' => 'Posting would over-receive or alter a purchase order item snapshot.']);
                }
            }

            $location = InventoryLocation::query()->where('tenant_id', $tenant->getKey())->findOrFail($receipt->inventory_location_id);
            $effectiveActor = $actor ?? $receipt->receiver;
            $this->inventory->handle($tenant, $location, $receiptItems->sortBy('variant_id')->map(
                fn (PurchaseReceiptItem $item): array => [
                    'product_variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'currency' => $orderItems[$item->purchase_order_item_id]->currency,
                    'reference_id' => $item->getKey(),
                ],
            )->values()->all(), $receipt->getKey(), $effectiveActor);

            $receipt->update(['status' => PurchaseReceiptStatus::Posted]);
            $this->statusUpdater->handle($tenant, $order);
            $totalCost = $receiptItems->reduce(
                fn (BigDecimal $total, PurchaseReceiptItem $item): BigDecimal => $total->plus(BigDecimal::of($item->unit_cost)->multipliedBy($item->quantity)),
                BigDecimal::zero(),
            )->toScale(4, RoundingMode::HalfUp);
            $this->audit->record(
                tenant: $tenant, actorType: $effectiveActor === null ? AuditActorType::System : AuditActorType::User,
                action: AuditAction::PurchaseReceiptPosted, subjectType: AuditSubjectType::PurchaseReceipt,
                subjectId: $receipt->getKey(),
                metadata: [
                    'purchase_order_number' => $order->number,
                    'quantity' => (int) $receiptItems->sum('quantity'),
                    'total_cost' => (string) $totalCost,
                    'currency' => $order->currency,
                ],
                actor: $effectiveActor,
                idempotencyKey: 'purchase_receipt.posted:'.$receipt->getKey(),
                subjectLabel: $receipt->reference ?? $receipt->getKey(),
            );
            $this->notifications->event(
                $tenant->getKey(), null, [PermissionKey::ProcurementView],
                'purchase-receipt-posted:'.$receipt->getKey(), NotificationType::PurchaseReceiptPosted, NotificationSeverity::Info,
                'Purchase receipt posted', 'Inventory from a purchase order was received.',
                '/admin/purchase-receipts/'.$receipt->getKey(), ['purchase_receipt_id' => $receipt->getKey()],
                AuditSubjectType::PurchaseReceipt->value, (string) $receipt->getKey(),
            );

            return $receipt->refresh()->load('items');
        });
    }
}
