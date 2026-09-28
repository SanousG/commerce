<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemovePurchaseOrderItem
{
    public function __construct(private readonly PurchaseOrderCalculator $calculator) {}

    public function handle(Tenant $tenant, PurchaseOrder $order, PurchaseOrderItem $item): PurchaseOrder
    {
        return DB::transaction(function () use ($tenant, $order, $item): PurchaseOrder {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if ($order->status !== PurchaseOrderStatus::Draft) {
                throw ValidationException::withMessages(['purchase_order' => 'Only draft purchase order items can be removed.']);
            }
            PurchaseOrderItem::query()->where('tenant_id', $tenant->getKey())->where('purchase_order_id', $order->getKey())->findOrFail($item->getKey())->delete();

            return $this->calculator->handle($order);
        });
    }
}
