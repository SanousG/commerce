<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkPurchaseOrderOrdered
{
    public function handle(Tenant $tenant, PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($tenant, $order): PurchaseOrder {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if ($order->status !== PurchaseOrderStatus::Approved) {
                throw ValidationException::withMessages(['purchase_order' => 'Only an approved purchase order can be marked ordered.']);
            }
            $order->update(['status' => PurchaseOrderStatus::Ordered, 'ordered_at' => now()]);

            return $order->refresh();
        });
    }
}
