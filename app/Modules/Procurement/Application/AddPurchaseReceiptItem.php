<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Procurement\Domain\Models\PurchaseReceiptItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddPurchaseReceiptItem
{
    public function handle(Tenant $tenant, PurchaseReceipt $receipt, PurchaseOrderItem $orderItem, int $quantity): PurchaseReceiptItem
    {
        ProcurementValues::positiveQuantity($quantity);

        return DB::transaction(function () use ($tenant, $receipt, $orderItem, $quantity): PurchaseReceiptItem {
            $receipt = PurchaseReceipt::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($receipt->getKey());
            if ($receipt->status !== PurchaseReceiptStatus::Draft) {
                throw ValidationException::withMessages(['purchase_receipt' => 'Items can only be added to a draft receipt.']);
            }
            $orderItem = PurchaseOrderItem::query()->where('tenant_id', $tenant->getKey())
                ->where('purchase_order_id', $receipt->purchase_order_id)->lockForUpdate()->findOrFail($orderItem->getKey());
            if ($quantity > $orderItem->quantity_ordered - $this->postedQuantity($tenant, $orderItem)) {
                throw ValidationException::withMessages(['quantity' => 'The receipt quantity exceeds the purchase order balance.']);
            }

            return $receipt->items()->create([
                'tenant_id' => $tenant->getKey(), 'purchase_order_id' => $receipt->purchase_order_id,
                'purchase_order_item_id' => $orderItem->getKey(), 'variant_id' => $orderItem->variant_id,
                'quantity' => $quantity, 'unit_cost' => $orderItem->unit_cost,
            ]);
        });
    }

    private function postedQuantity(Tenant $tenant, PurchaseOrderItem $item): int
    {
        return (int) DB::table('purchase_receipt_items as pri')->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
            ->where('pri.tenant_id', $tenant->getKey())->where('pri.purchase_order_item_id', $item->getKey())
            ->where('pr.status', PurchaseReceiptStatus::Posted->value)->sum('pri.quantity');
    }
}
