<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePurchaseOrderItem
{
    public function __construct(private readonly PurchaseOrderCalculator $calculator) {}

    public function handle(Tenant $tenant, PurchaseOrder $order, PurchaseOrderItem $item, int $quantity, string|int|float|null $unitCost = null, ?string $description = null): PurchaseOrderItem
    {
        return DB::transaction(function () use ($tenant, $order, $item, $quantity, $unitCost, $description): PurchaseOrderItem {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if ($order->status !== PurchaseOrderStatus::Draft) {
                throw ValidationException::withMessages(['purchase_order' => 'Only draft purchase order items can be edited.']);
            }
            $item = PurchaseOrderItem::query()->where('tenant_id', $tenant->getKey())->where('purchase_order_id', $order->getKey())
                ->lockForUpdate()->findOrFail($item->getKey());
            $supplierProduct = $item->supplierProduct()->lockForUpdate()->firstOrFail();
            if ($quantity < $supplierProduct->minimum_order_quantity
                || ($supplierProduct->order_multiple !== null && $quantity % $supplierProduct->order_multiple !== 0)) {
                throw ValidationException::withMessages(['quantity' => 'The quantity must meet the supplier MOQ and order multiple.']);
            }
            $cost = ProcurementValues::amount($unitCost ?? $item->unit_cost);
            $item->update([
                'quantity_ordered' => $quantity, 'unit_cost' => $cost, 'description' => $description,
                'line_total' => (string) BigDecimal::of($cost)->multipliedBy($quantity)->toScale(4),
            ]);
            $this->calculator->handle($order);

            return $item->refresh();
        });
    }
}
