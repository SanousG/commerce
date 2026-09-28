<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Procurement\Application\AddPurchaseOrderItem;
use App\Modules\Procurement\Application\ApprovePurchaseOrder;
use App\Modules\Procurement\Application\CancelPurchaseOrder;
use App\Modules\Procurement\Application\CreatePurchaseOrder;
use App\Modules\Procurement\Application\MarkPurchaseOrderOrdered;
use App\Modules\Procurement\Application\UpdatePurchaseOrder;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseOrderItem;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Procurement\Http\Requests\ApprovePurchaseOrderRequest;
use App\Modules\Procurement\Http\Requests\CreatePurchaseOrderRequest;
use App\Modules\Procurement\Http\Requests\ProcurementActionRequest;
use App\Modules\Procurement\Http\Requests\UpdatePurchaseOrderRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request, TenantContext $tenants): Response
    {
        $search = trim((string) $request->query('search'));
        $supplierId = trim((string) $request->query('supplier_id'));
        $status = collect(PurchaseOrderStatus::cases())->firstWhere('value', $request->query('status'))?->value ?? '';
        $orders = PurchaseOrder::query()->where('tenant_id', $tenants->id())
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('number', 'ilike', "%{$search}%")
                ->orWhereHas('supplier', fn ($query) => $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%"))
                ->orWhereHas('items', fn ($query) => $query->where('sku', 'ilike', "%{$search}%")
                    ->orWhere('supplier_sku', 'ilike', "%{$search}%"))))
            ->when($supplierId !== '', fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with('supplier:id,name')->orderByDesc('created_at')->paginate(20)->withQueryString();
        $progress = $this->receivedProgress($tenants, collect($orders->items())->pluck('id'));
        $orders->through(function (PurchaseOrder $order) use ($progress): array {
            $received = (int) ($progress->get($order->getKey())?->received_quantity ?? 0);
            $ordered = (int) ($progress->get($order->getKey())?->ordered_quantity ?? 0);

            return [
                'id' => $order->getKey(), 'number' => $order->number, 'supplier_name' => $order->supplier->name,
                'status' => $order->status->value, 'currency' => $order->currency, 'total' => $order->grand_total,
                'grand_total' => $order->grand_total, 'ordered_at' => $order->ordered_at?->toISOString(),
                'expected_at' => $order->expected_at?->toISOString(), 'created_at' => $order->created_at?->toISOString(),
                'ordered_quantity' => $ordered, 'received_quantity' => $received,
                'remaining_quantity' => $ordered - $received,
            ];
        });

        return Inertia::render('admin/purchase-orders/Index', [
            'purchaseOrders' => $orders,
            'filters' => ['search' => $search, 'supplier_id' => $supplierId, 'status' => $status],
            'suppliers' => Supplier::query()->where('tenant_id', $tenants->id())->orderBy('name')->get(['id', 'name']),
            'statuses' => array_column(PurchaseOrderStatus::cases(), 'value'),
        ]);
    }

    public function create(TenantContext $tenants): Response
    {
        $suppliers = Supplier::query()->where('tenant_id', $tenants->id())->where('status', SupplierStatus::Active)
            ->with(['products' => fn ($query) => $query->where('status', SupplierProductStatus::Active)
                ->whereHas('variant', fn ($query) => $query->where('status', ProductVariantStatus::Active))
                ->with('variant.product')->orderByDesc('is_preferred')])
            ->orderBy('name')->get();

        return Inertia::render('admin/purchase-orders/Create', [
            'suppliers' => $suppliers->map(fn (Supplier $supplier): array => [
                'id' => $supplier->getKey(), 'name' => $supplier->name, 'code' => $supplier->code,
                'supplier_products' => $supplier->products->map(fn (SupplierProduct $product): array => $this->serializeSupplierProduct($product)),
            ]),
        ]);
    }

    public function store(
        CreatePurchaseOrderRequest $request,
        TenantContext $tenants,
        CreatePurchaseOrder $createOrder,
        AddPurchaseOrderItem $addItem,
    ): RedirectResponse {
        $data = $request->validated();
        $tenant = $tenants->get();
        $supplier = Supplier::query()->where('tenant_id', $tenants->id())->findOrFail($data['supplier_id']);
        $products = SupplierProduct::query()->where('tenant_id', $tenants->id())
            ->where('supplier_id', $supplier->getKey())->whereIn('id', collect($data['items'])->pluck('supplier_product_id'))
            ->get()->keyBy('id');
        abort_unless($products->count() === count($data['items']), 404);
        $currency = $data['currency'];

        $order = DB::transaction(function () use ($tenant, $supplier, $currency, $data, $request, $createOrder, $addItem): PurchaseOrder {
            $order = $createOrder->handle($tenant, $supplier, $currency, $data['expected_at'], $data['notes'], $request->user());
            foreach ($data['items'] as $line) {
                $product = SupplierProduct::query()->where('tenant_id', $tenant->getKey())
                    ->where('supplier_id', $supplier->getKey())->findOrFail($line['supplier_product_id']);
                $addItem->handle(
                    $tenant, $order, $product, $line['quantity'], $line['unit_cost'] ?? null, $line['description'] ?? null,
                );
            }

            return $order;
        });

        return to_route('admin.purchase-orders.show', $order)->with('status', 'Purchase order draft created.');
    }

    public function show(Request $request, TenantContext $tenants, string $purchaseOrder): Response
    {
        $order = $this->order($tenants, $purchaseOrder);
        $order->load(['supplier', 'items', 'receipts.location', 'receipts.items.purchaseOrderItem']);
        $received = DB::table('purchase_receipt_items as pri')->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
            ->where('pri.tenant_id', $tenants->id())->where('pri.purchase_order_id', $order->getKey())
            ->where('pr.status', PurchaseReceiptStatus::Posted->value)->groupBy('pri.purchase_order_item_id')
            ->selectRaw('pri.purchase_order_item_id, SUM(pri.quantity) AS quantity')->pluck('quantity', 'purchase_order_item_id');
        $canManage = $request->user()->can('procurement.manage');
        $canReceive = $request->user()->can('procurement.receive');
        $hasPostedReceipt = $order->receipts->contains(fn (PurchaseReceipt $receipt): bool => $receipt->status === PurchaseReceiptStatus::Posted);
        $isDraft = $order->status === PurchaseOrderStatus::Draft;
        $eligibleProducts = $isDraft ? SupplierProduct::query()->where('tenant_id', $tenants->id())
            ->where('supplier_id', $order->supplier_id)->where('currency', $order->currency)
            ->where('status', SupplierProductStatus::Active)->whereNotIn('id', $order->items->pluck('supplier_product_id'))
            ->whereHas('variant', fn ($query) => $query->where('status', ProductVariantStatus::Active))
            ->with('variant.product')->orderByDesc('is_preferred')->get() : collect();

        return Inertia::render('admin/purchase-orders/Show', [
            'purchaseOrder' => [
                'id' => $order->getKey(), 'number' => $order->number, 'status' => $order->status->value,
                'currency' => $order->currency, 'supplier_name' => $order->supplier->name,
                'supplier_code' => $order->supplier->code, 'expected_at' => $order->expected_at?->toISOString(),
                'ordered_at' => $order->ordered_at?->toISOString(), 'cancelled_at' => $order->cancelled_at?->toISOString(),
                'completed_at' => $order->completed_at?->toISOString(), 'approved_at' => null, 'closed_at' => $order->completed_at?->toISOString(),
                'notes' => $order->notes, 'subtotal' => $order->subtotal, 'discount_total' => $order->discount_total,
                'shipping_total' => $order->shipping_total, 'tax_total' => $order->tax_total,
                'grand_total' => $order->grand_total, 'total' => $order->grand_total,
            ],
            'items' => $order->items->map(function (PurchaseOrderItem $item) use ($received): array {
                $receivedQuantity = (int) ($received[$item->getKey()] ?? 0);

                return [
                    'id' => $item->getKey(), 'supplier_product_id' => $item->supplier_product_id,
                    'product_name' => $item->product_name, 'variant_name' => $item->variant_name,
                    'sku' => $item->sku, 'supplier_sku' => $item->supplier_sku,
                    'description' => $item->description, 'quantity_ordered' => $item->quantity_ordered,
                    'quantity' => $item->quantity_ordered, 'received_quantity' => $receivedQuantity,
                    'remaining_quantity' => $item->quantity_ordered - $receivedQuantity,
                    'unit_cost' => $item->unit_cost, 'currency' => $item->currency, 'line_total' => $item->line_total,
                ];
            }),
            'receipts' => $order->receipts->map(fn (PurchaseReceipt $receipt): array => [
                'id' => $receipt->getKey(), 'number' => $receipt->reference ?? $receipt->getKey(),
                'status' => $receipt->status->value, 'inventory_location_id' => $receipt->inventory_location_id,
                'location_name' => $receipt->location->name, 'location_code' => $receipt->location->code,
                'reference' => $receipt->reference, 'notes' => $receipt->notes,
                'received_at' => $receipt->received_at->toISOString(),
                'items' => $receipt->items->map(fn ($item): array => [
                    'id' => $item->getKey(), 'purchase_order_item_id' => $item->purchase_order_item_id,
                    'product_name' => $item->purchaseOrderItem->product_name, 'sku' => $item->purchaseOrderItem->sku,
                    'quantity' => $item->quantity, 'unit_cost' => $item->unit_cost,
                ]),
            ]),
            'locations' => InventoryLocation::query()->where('tenant_id', $tenants->id())
                ->where('status', InventoryLocationStatus::Active)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'code']),
            'supplierProducts' => $eligibleProducts->map(fn (SupplierProduct $product): array => $this->serializeSupplierProduct($product)),
            'statuses' => array_column(PurchaseOrderStatus::cases(), 'value'),
            'capabilities' => [
                'update' => $isDraft && $canManage, 'update_items' => $isDraft && $canManage,
                'approve' => $isDraft && $request->user()->can('procurement.approve'),
                'mark_ordered' => $order->status === PurchaseOrderStatus::Approved && $canManage,
                'cancel' => $canManage && in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Ordered], true) && ! $hasPostedReceipt,
                'receive' => $canReceive && in_array($order->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true),
            ],
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, TenantContext $tenants, UpdatePurchaseOrder $action, string $purchaseOrder): RedirectResponse
    {
        $data = $request->validated();
        $order = $this->order($tenants, $purchaseOrder);
        $action->handle($tenants->get(), $order, $data['expected_at'], $data['notes']);

        return back()->with('status', 'Purchase order updated.');
    }

    public function approve(ApprovePurchaseOrderRequest $request, TenantContext $tenants, ApprovePurchaseOrder $action, string $purchaseOrder): RedirectResponse
    {
        $action->handle($tenants->get(), $this->order($tenants, $purchaseOrder), $request->user());

        return back()->with('status', 'Purchase order approved.');
    }

    public function markOrdered(ProcurementActionRequest $request, TenantContext $tenants, MarkPurchaseOrderOrdered $action, string $purchaseOrder): RedirectResponse
    {
        $action->handle($tenants->get(), $this->order($tenants, $purchaseOrder));

        return back()->with('status', 'Purchase order marked as ordered.');
    }

    public function cancel(ProcurementActionRequest $request, TenantContext $tenants, CancelPurchaseOrder $action, string $purchaseOrder): RedirectResponse
    {
        $action->handle($tenants->get(), $this->order($tenants, $purchaseOrder));

        return back()->with('status', 'Purchase order cancelled.');
    }

    private function order(TenantContext $tenants, string $id): PurchaseOrder
    {
        return PurchaseOrder::query()->where('tenant_id', $tenants->id())->findOrFail($id);
    }

    private function receivedProgress(TenantContext $tenants, Collection $orderIds): Collection
    {
        if ($orderIds->isEmpty()) {
            return collect();
        }

        $ordered = DB::table('purchase_order_items')->where('tenant_id', $tenants->id())
            ->whereIn('purchase_order_id', $orderIds)->groupBy('purchase_order_id')
            ->selectRaw('purchase_order_id, SUM(quantity_ordered) AS ordered_quantity')->get()->keyBy('purchase_order_id');
        $received = DB::table('purchase_receipt_items as pri')->join('purchase_receipts as pr', 'pr.id', '=', 'pri.purchase_receipt_id')
            ->where('pri.tenant_id', $tenants->id())->whereIn('pri.purchase_order_id', $orderIds)
            ->where('pr.status', PurchaseReceiptStatus::Posted->value)->groupBy('pri.purchase_order_id')
            ->selectRaw('pri.purchase_order_id, SUM(pri.quantity) AS received_quantity')->pluck('received_quantity', 'purchase_order_id');

        return $ordered->map(function ($row) use ($received) {
            $row->received_quantity = (int) ($received[$row->purchase_order_id] ?? 0);

            return $row;
        });
    }

    private function serializeSupplierProduct(SupplierProduct $product): array
    {
        return [
            'id' => $product->getKey(), 'product_name' => $product->variant->product->name,
            'variant_name' => $product->variant->name, 'sku' => $product->variant->sku,
            'supplier_sku' => $product->supplier_sku, 'unit_cost' => $product->unit_cost,
            'currency' => $product->currency, 'minimum_order_quantity' => $product->minimum_order_quantity,
            'order_multiple' => $product->order_multiple, 'lead_time_days' => $product->lead_time_days,
        ];
    }
}
