<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Inventory\Application\ReceiveInventory;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Procurement\Domain\Models\PurchaseReceiptItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostPurchaseReceipt
{
    public function __construct(
        private readonly ReceiveInventory $inventory,
        private readonly PurchaseOrderReceivingStatusUpdater $statusUpdater,
    ) {}

    public function handle(Tenant $tenant, PurchaseReceipt $receipt): PurchaseReceipt
    {
        return DB::transaction(function () use ($tenant, $receipt): PurchaseReceipt {
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
            $this->inventory->handle($tenant, $location, $receiptItems->sortBy('variant_id')->map(fn (PurchaseReceiptItem $item): array => [
                'product_variant_id' => $item->variant_id,
                'quantity' => $item->quantity,
                'reference_id' => $item->getKey(),
            ])->values()->all(), $receipt->getKey(), $receipt->receiver);

            $receipt->update(['status' => PurchaseReceiptStatus::Posted]);
            $this->statusUpdater->handle($tenant, $order);

            return $receipt->refresh()->load('items');
        });
    }
}
