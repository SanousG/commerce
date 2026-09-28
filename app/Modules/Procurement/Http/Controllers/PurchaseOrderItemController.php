<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Application\AddPurchaseOrderItem;
use App\Modules\Procurement\Application\RemovePurchaseOrderItem;
use App\Modules\Procurement\Application\UpdatePurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Procurement\Http\Requests\ProcurementActionRequest;
use App\Modules\Procurement\Http\Requests\PurchaseOrderItemRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class PurchaseOrderItemController extends Controller
{
    public function store(PurchaseOrderItemRequest $request, TenantContext $tenants, AddPurchaseOrderItem $action, string $purchaseOrder): RedirectResponse
    {
        $data = $request->validated();
        $order = PurchaseOrder::query()->where('tenant_id', $tenants->id())->findOrFail($purchaseOrder);
        $product = SupplierProduct::query()->where('tenant_id', $tenants->id())->findOrFail($data['supplier_product_id']);
        $action->handle(
            $tenants->get(), $order, $product, $data['quantity'], $data['unit_cost'] ?? null, $data['description'] ?? null,
        );

        return back()->with('status', 'Purchase order item added.');
    }

    public function update(PurchaseOrderItemRequest $request, TenantContext $tenants, UpdatePurchaseOrderItem $action, string $item): RedirectResponse
    {
        $data = $request->validated();
        $scoped = $this->item($tenants, $item);
        $order = PurchaseOrder::query()->where('tenant_id', $tenants->id())->findOrFail($scoped->purchase_order_id);
        $action->handle(
            $tenants->get(), $order, $scoped, $data['quantity'], $data['unit_cost'] ?? null,
            $data['description'] ?? $scoped->description,
        );

        return back()->with('status', 'Purchase order item updated.');
    }

    public function destroy(ProcurementActionRequest $request, TenantContext $tenants, RemovePurchaseOrderItem $action, string $item): RedirectResponse
    {
        $scoped = $this->item($tenants, $item);
        $order = PurchaseOrder::query()->where('tenant_id', $tenants->id())->findOrFail($scoped->purchase_order_id);
        $action->handle($tenants->get(), $order, $scoped);

        return back()->with('status', 'Purchase order item removed.');
    }

    private function item(TenantContext $tenants, string $id): PurchaseOrderItem
    {
        return PurchaseOrderItem::query()->where('tenant_id', $tenants->id())->findOrFail($id);
    }
}
