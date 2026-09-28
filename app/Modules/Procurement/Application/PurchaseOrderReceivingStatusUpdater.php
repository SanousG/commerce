<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class PurchaseOrderReceivingStatusUpdater
{
    public function handle(Tenant $tenant, PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($tenant, $order): PurchaseOrder {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            $items = PurchaseOrderItem::query()->where('tenant_id', $tenant->getKey())
                ->where('purchase_order_id', $order->getKey())->orderBy('id')->lockForUpdate()->get();
            $received = DB::table('purchase_receipt_items as pri')->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
                ->where('pri.tenant_id', $tenant->getKey())->where('pri.purchase_order_id', $order->getKey())
                ->where('pr.status', PurchaseReceiptStatus::Posted->value)->groupBy('pri.purchase_order_item_id')
                ->selectRaw('pri.purchase_order_item_id, SUM(pri.quantity) AS quantity')->get()->keyBy('purchase_order_item_id');
            $fullyReceived = $items->isNotEmpty() && $items->every(
                fn (PurchaseOrderItem $item): bool => (int) ($received->get($item->getKey())?->quantity ?? 0) === $item->quantity_ordered,
            );
            $order->update([
                'status' => $fullyReceived ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
                'completed_at' => $fullyReceived ? now() : null,
            ]);

            return $order->refresh();
        });
    }
}
