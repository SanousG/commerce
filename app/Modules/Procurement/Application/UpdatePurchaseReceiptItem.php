<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Procurement\Domain\Models\PurchaseReceiptItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePurchaseReceiptItem
{
    public function handle(Tenant $tenant, PurchaseReceipt $receipt, PurchaseReceiptItem $item, int $quantity): PurchaseReceiptItem
    {
        ProcurementValues::positiveQuantity($quantity);

        return DB::transaction(function () use ($tenant, $receipt, $item, $quantity): PurchaseReceiptItem {
            $receipt = PurchaseReceipt::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($receipt->getKey());
            if ($receipt->status !== PurchaseReceiptStatus::Draft) {
                throw ValidationException::withMessages(['purchase_receipt' => 'Only draft receipt items can be edited.']);
            }
            $item = PurchaseReceiptItem::query()->where('tenant_id', $tenant->getKey())->where('purchase_receipt_id', $receipt->getKey())
                ->lockForUpdate()->findOrFail($item->getKey());
            $orderItem = PurchaseOrderItem::query()->where('tenant_id', $tenant->getKey())->where('purchase_order_id', $receipt->purchase_order_id)
                ->lockForUpdate()->findOrFail($item->purchase_order_item_id);
            $received = (int) DB::table('purchase_receipt_items as pri')->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                ->where('pri.tenant_id', $tenant->getKey())->where('pri.purchase_order_item_id', $orderItem->getKey())
                ->where('pr.status', PurchaseReceiptStatus::Posted->value)->sum('pri.quantity');
            if ($quantity > $orderItem->quantity_ordered - $received) {
                throw ValidationException::withMessages(['quantity' => 'The receipt quantity exceeds the purchase order balance.']);
            }
            $item->update(['quantity' => $quantity]);

            return $item->refresh();
        });
    }
}
