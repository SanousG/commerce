<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddPurchaseOrderItem
{
    public function __construct(private readonly PurchaseOrderCalculator $calculator) {}

    public function handle(Tenant $tenant, PurchaseOrder $order, SupplierProduct $supplierProduct, int $quantity, string|int|float|null $unitCost = null, ?string $description = null): PurchaseOrderItem
    {
        return DB::transaction(function () use ($tenant, $order, $supplierProduct, $quantity, $unitCost, $description): PurchaseOrderItem {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            $supplierProduct = SupplierProduct::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($supplierProduct->getKey());
            $this->validate($order, $supplierProduct, $quantity);
            $variant = $supplierProduct->variant()->with('product')->firstOrFail();
            if ($variant->status !== ProductVariantStatus::Active) {
                throw ValidationException::withMessages(['supplier_product_id' => 'Only an active product variant can be added to a purchase order.']);
            }
            $cost = ProcurementValues::amount($unitCost ?? $supplierProduct->unit_cost);
            $item = $order->items()->create([
                'tenant_id' => $tenant->getKey(), 'supplier_product_id' => $supplierProduct->getKey(),
                'product_id' => $variant->product_id, 'variant_id' => $variant->getKey(), 'supplier_sku' => $supplierProduct->supplier_sku,
                'product_name' => $variant->product->name, 'variant_name' => $variant->name, 'sku' => $variant->sku,
                'description' => $description, 'quantity_ordered' => $quantity, 'unit_cost' => $cost, 'currency' => $order->currency,
                'line_total' => (string) BigDecimal::of($cost)->multipliedBy($quantity)->toScale(4),
            ]);
            $this->calculator->handle($order);

            return $item->refresh();
        });
    }

    private function validate(PurchaseOrder $order, SupplierProduct $product, int $quantity): void
    {
        if ($order->status !== PurchaseOrderStatus::Draft || $product->status !== SupplierProductStatus::Active
            || $product->supplier_id !== $order->supplier_id || $product->currency !== $order->currency) {
            throw ValidationException::withMessages(['supplier_product_id' => 'The supplier product is not eligible for this draft purchase order.']);
        }
        if ($quantity < $product->minimum_order_quantity || ($product->order_multiple !== null && $quantity % $product->order_multiple !== 0)) {
            throw ValidationException::withMessages(['quantity' => 'The quantity must meet the supplier MOQ and order multiple.']);
        }
    }
}
