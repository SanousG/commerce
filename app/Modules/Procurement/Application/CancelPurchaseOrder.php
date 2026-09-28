<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPurchaseOrder
{
    public function handle(Tenant $tenant, PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($tenant, $order): PurchaseOrder {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if (! in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Ordered], true)
                || $order->receipts()->where('status', PurchaseReceiptStatus::Posted)->exists()) {
                throw ValidationException::withMessages(['purchase_order' => 'Only an order with no received quantities can be cancelled.']);
            }
            $order->receipts()->where('status', PurchaseReceiptStatus::Draft)->update(['status' => PurchaseReceiptStatus::Cancelled]);
            $order->update(['status' => PurchaseOrderStatus::Cancelled, 'cancelled_at' => now()]);

            return $order->refresh();
        });
    }
}
