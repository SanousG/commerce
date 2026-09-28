<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Application\AddPurchaseReceiptItem;
use App\Modules\Procurement\Application\RemovePurchaseReceiptItem;
use App\Modules\Procurement\Application\UpdatePurchaseReceiptItem;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Procurement\Domain\Models\PurchaseReceiptItem;
use App\Modules\Procurement\Http\Requests\PurchaseReceiptItemRequest;
use App\Modules\Procurement\Http\Requests\ReceiveProcurementRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class PurchaseReceiptItemController extends Controller
{
    public function store(PurchaseReceiptItemRequest $request, TenantContext $tenants, AddPurchaseReceiptItem $action, string $receipt): RedirectResponse
    {
        $data = $request->validated();
        $scopedReceipt = PurchaseReceipt::query()->where('tenant_id', $tenants->id())->findOrFail($receipt);
        $orderItem = PurchaseOrderItem::query()->where('tenant_id', $tenants->id())
            ->where('purchase_order_id', $scopedReceipt->purchase_order_id)->findOrFail($data['purchase_order_item_id']);
        $action->handle($tenants->get(), $scopedReceipt, $orderItem, $data['quantity']);

        return back()->with('status', 'Purchase receipt item added.');
    }

    public function update(PurchaseReceiptItemRequest $request, TenantContext $tenants, UpdatePurchaseReceiptItem $action, string $item): RedirectResponse
    {
        $data = $request->validated();
        $scoped = $this->item($tenants, $item);
        $receipt = PurchaseReceipt::query()->where('tenant_id', $tenants->id())->findOrFail($scoped->purchase_receipt_id);
        $action->handle($tenants->get(), $receipt, $scoped, $data['quantity']);

        return back()->with('status', 'Purchase receipt item updated.');
    }

    public function destroy(ReceiveProcurementRequest $request, TenantContext $tenants, RemovePurchaseReceiptItem $action, string $item): RedirectResponse
    {
        $scoped = $this->item($tenants, $item);
        $receipt = PurchaseReceipt::query()->where('tenant_id', $tenants->id())->findOrFail($scoped->purchase_receipt_id);
        $action->handle($tenants->get(), $receipt, $scoped);

        return back()->with('status', 'Purchase receipt item removed.');
    }

    private function item(TenantContext $tenants, string $id): PurchaseReceiptItem
    {
        return PurchaseReceiptItem::query()->where('tenant_id', $tenants->id())->findOrFail($id);
    }
}
