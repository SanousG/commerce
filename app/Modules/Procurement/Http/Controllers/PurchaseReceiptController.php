<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Procurement\Application\AddPurchaseReceiptItem;
use App\Modules\Procurement\Application\CancelPurchaseReceipt;
use App\Modules\Procurement\Application\CreatePurchaseReceipt;
use App\Modules\Procurement\Application\PostPurchaseReceipt;
use App\Modules\Procurement\Application\UpdatePurchaseReceipt;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Procurement\Http\Requests\PurchaseReceiptRequest;
use App\Modules\Procurement\Http\Requests\ReceiveProcurementRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PurchaseReceiptController extends Controller
{
    public function store(
        PurchaseReceiptRequest $request,
        TenantContext $tenants,
        CreatePurchaseReceipt $createReceipt,
        AddPurchaseReceiptItem $addItem,
        string $purchaseOrder,
    ): RedirectResponse {
        $data = $request->validated();
        $tenant = $tenants->get();
        $order = PurchaseOrder::query()->where('tenant_id', $tenants->id())->findOrFail($purchaseOrder);
        $location = InventoryLocation::query()->where('tenant_id', $tenants->id())->findOrFail($data['inventory_location_id']);

        $receipt = DB::transaction(function () use ($tenant, $order, $location, $data, $request, $createReceipt, $addItem): PurchaseReceipt {
            $receipt = $createReceipt->handle(
                $tenant, $order, $location, $data['received_at'], $request->user(), $data['reference'], $data['notes'],
            );
            foreach ($data['items'] ?? [] as $line) {
                $orderItem = PurchaseOrderItem::query()->where('tenant_id', $tenant->getKey())
                    ->where('purchase_order_id', $order->getKey())->findOrFail($line['purchase_order_item_id']);
                $addItem->handle($tenant, $receipt, $orderItem, $line['quantity']);
            }

            return $receipt;
        });

        return to_route('admin.purchase-orders.show', $order)
            ->with('status', "Draft receipt {$receipt->getKey()} created. Review and post it to receive inventory.");
    }

    public function show(TenantContext $tenants, string $receipt): RedirectResponse
    {
        $scoped = $this->receipt($tenants, $receipt);

        return to_route('admin.purchase-orders.show', $scoped->purchase_order_id);
    }

    public function update(PurchaseReceiptRequest $request, TenantContext $tenants, UpdatePurchaseReceipt $action, string $receipt): RedirectResponse
    {
        $data = $request->validated();
        $scoped = $this->receipt($tenants, $receipt);
        $location = InventoryLocation::query()->where('tenant_id', $tenants->id())->findOrFail($data['inventory_location_id']);
        $action->handle(
            $tenants->get(), $scoped, $location, $data['received_at'], $request->user(), $data['reference'], $data['notes'],
        );

        return back()->with('status', 'Purchase receipt updated.');
    }

    public function post(ReceiveProcurementRequest $request, TenantContext $tenants, PostPurchaseReceipt $action, string $receipt): RedirectResponse
    {
        $action->handle($tenants->get(), $this->receipt($tenants, $receipt));

        return back()->with('status', 'Purchase receipt posted and inventory received.');
    }

    public function cancel(ReceiveProcurementRequest $request, TenantContext $tenants, CancelPurchaseReceipt $action, string $receipt): RedirectResponse
    {
        $action->handle($tenants->get(), $this->receipt($tenants, $receipt));

        return back()->with('status', 'Purchase receipt cancelled.');
    }

    private function receipt(TenantContext $tenants, string $id): PurchaseReceipt
    {
        return PurchaseReceipt::query()->where('tenant_id', $tenants->id())->findOrFail($id);
    }
}
