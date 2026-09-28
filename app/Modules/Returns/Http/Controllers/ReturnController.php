<?php

namespace App\Modules\Returns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Returns\Application\ApproveReturn;
use App\Modules\Returns\Application\CancelReturn;
use App\Modules\Returns\Application\CompleteReturn;
use App\Modules\Returns\Application\CreateReturn;
use App\Modules\Returns\Application\ReceiveReturn;
use App\Modules\Returns\Application\RejectReturn;
use App\Modules\Returns\Application\ReturnEligibility;
use App\Modules\Returns\Domain\Enums\ReturnItemCondition;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use App\Modules\Returns\Domain\Models\Exchange;
use App\Modules\Returns\Domain\Models\ReturnItem;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Returns\Domain\Models\ReturnStatusHistory;
use App\Modules\Returns\Http\Requests\CancelReturnRequest;
use App\Modules\Returns\Http\Requests\CreateReturnRequest;
use App\Modules\Returns\Http\Requests\ManageReturnRequest;
use App\Modules\Returns\Http\Requests\ReceiveReturnRequest;
use App\Modules\Returns\Http\Requests\RejectReturnRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function index(Request $request, TenantContext $tenants, StoreContext $stores): Response
    {
        abort_unless($request->user()->can('returns.view'), 403);
        $returns = ReturnRequest::query()
            ->where('tenant_id', $tenants->id())->where('store_id', $stores->id())
            ->with('order:id,order_number,customer_name,email')
            ->withCount('items')->orderByDesc('requested_at')->paginate(20)->withQueryString();
        $returns->through(fn (ReturnRequest $return): array => [
            'id' => $return->getKey(), 'short_id' => substr((string) $return->getKey(), 0, 8),
            'order_id' => $return->order_id, 'order_number' => $return->order->order_number,
            'customer_name' => $return->order->customer_name ?: 'Guest',
            'customer_email' => $return->order->email, 'status' => $return->status->value,
            'requested_at' => $return->requested_at?->toISOString(), 'item_count' => $return->items_count,
        ]);

        return Inertia::render('admin/returns/Index', ['returns' => $returns]);
    }

    public function show(
        Request $request,
        TenantContext $tenants,
        StoreContext $stores,
        ReturnEligibility $eligibility,
        string $return,
    ): Response {
        abort_unless($request->user()->can('returns.view'), 403);
        $scoped = $this->returnRequest($tenants, $stores, $return);
        $scoped->load([
            'order', 'items.orderItem',
            'history' => fn ($query) => $query->with('actor:id,name')->orderBy('created_at'),
            'exchanges' => fn ($query) => $query->with('items')->orderByDesc('created_at'),
        ]);
        $canViewRefunds = $request->user()->can('refunds.view');
        if ($canViewRefunds) {
            $scoped->load(['refunds' => fn ($query) => $query->orderByDesc('created_at')]);
        }
        $locations = $tenants->get()->inventoryLocations()
            ->where('status', InventoryLocationStatus::Active)->orderByDesc('is_default')->orderBy('name')
            ->get(['id', 'name', 'code']);
        $replacementVariants = ProductVariant::query()->where('tenant_id', $tenants->id())
            ->where('status', ProductVariantStatus::Active)->with('product:id,name')
            ->orderBy('sku')->get(['id', 'product_id', 'name', 'sku'])
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->getKey(), 'product_name' => $variant->product->name,
                'name' => $variant->name, 'sku' => $variant->sku,
            ]);
        $canManage = $request->user()->can('returns.manage');
        $canInspect = $request->user()->can('returns.inspect');
        $canRestock = $request->user()->can('returns.restock');
        $canManageExchanges = $request->user()->can('exchanges.manage');

        return Inertia::render('admin/returns/Show', [
            'return' => $this->serialize($scoped, $eligibility, $tenants),
            'refunds' => $canViewRefunds
                ? $scoped->refunds->map(fn (Refund $refund): array => $this->refund($refund))->values()
                : null,
            'exchanges' => $scoped->exchanges->map(fn (Exchange $exchange): array => $this->exchange($exchange))->values(),
            'locations' => $locations,
            'replacementVariants' => $replacementVariants,
            'conditions' => array_column(ReturnItemCondition::cases(), 'value'),
            'dispositions' => array_column(ReturnItemDisposition::cases(), 'value'),
            'capabilities' => [
                'manage' => $canManage, 'inspect' => $canInspect, 'restock' => $canRestock,
                'view_refunds' => $canViewRefunds, 'manage_exchanges' => $canManageExchanges,
                'approve' => $canManage && $scoped->status === ReturnStatus::Requested,
                'reject' => $canManage && $scoped->status === ReturnStatus::Requested,
                'receive' => $canManage && $scoped->status === ReturnStatus::Approved,
                'complete' => $canManage && $scoped->status === ReturnStatus::Inspecting,
                'cancel' => $canManage && in_array($scoped->status, [ReturnStatus::Requested, ReturnStatus::Approved], true),
                'create_exchange' => $canManageExchanges
                    && in_array($scoped->status, [ReturnStatus::Inspecting, ReturnStatus::Completed], true),
            ],
        ]);
    }

    public function store(
        CreateReturnRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CreateReturn $action,
        string $order,
    ): RedirectResponse {
        $scopedOrder = $this->order($tenants, $stores, $order);
        /** @var list<array{order_item_id: string, quantity: int, reason?: string|null}> $items */
        $items = $request->validated('items');
        $return = $action->handle(
            $tenants->get(), $stores->get(), $scopedOrder, $items,
            $request->validated('reason'), null, $request->validated('notes'), $request->user(),
        );

        return to_route('admin.returns.show', $return)->with('status', 'Return requested.');
    }

    public function approve(
        ManageReturnRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        ApproveReturn $action,
        string $return,
    ): RedirectResponse {
        $action->handle(
            $tenants->get(), $stores->get(), $this->returnRequest($tenants, $stores, $return), $request->user(),
        );

        return back()->with('status', 'Return approved.');
    }

    public function reject(
        RejectReturnRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        RejectReturn $action,
        string $return,
    ): RedirectResponse {
        $action->handle(
            $tenants->get(), $stores->get(), $this->returnRequest($tenants, $stores, $return),
            $request->validated('reason'), $request->user(),
        );

        return back()->with('status', 'Return rejected.');
    }

    public function receive(
        ReceiveReturnRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        ReceiveReturn $action,
        string $return,
    ): RedirectResponse {
        /** @var list<array{return_item_id: string, quantity: int}> $items */
        $items = $request->validated('items');
        $action->handle(
            $tenants->get(), $stores->get(), $this->returnRequest($tenants, $stores, $return),
            $items, $request->user(),
        );

        return back()->with('status', 'Return received.');
    }

    public function complete(
        ManageReturnRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CompleteReturn $action,
        string $return,
    ): RedirectResponse {
        $action->handle(
            $tenants->get(), $stores->get(), $this->returnRequest($tenants, $stores, $return), $request->user(),
        );

        return back()->with('status', 'Return completed.');
    }

    public function cancel(
        CancelReturnRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CancelReturn $action,
        string $return,
    ): RedirectResponse {
        $action->handle(
            $tenants->get(), $stores->get(), $this->returnRequest($tenants, $stores, $return),
            $request->validated('reason'), $request->user(),
        );

        return back()->with('status', 'Return cancelled.');
    }

    private function order(TenantContext $tenants, StoreContext $stores, string $id): Order
    {
        return Order::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }

    private function returnRequest(TenantContext $tenants, StoreContext $stores, string $id): ReturnRequest
    {
        return ReturnRequest::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }

    private function serialize(
        ReturnRequest $return,
        ReturnEligibility $eligibility,
        TenantContext $tenants,
    ): array {
        return [
            'id' => $return->getKey(), 'short_id' => substr((string) $return->getKey(), 0, 8),
            'status' => $return->status->value, 'reason' => $return->reason,
            'notes' => $return->internal_note, 'rejection_reason' => $return->rejection_reason,
            'cancellation_reason' => $return->cancellation_reason,
            'requested_at' => $return->requested_at?->toISOString(),
            'approved_at' => $return->approved_at?->toISOString(),
            'rejected_at' => $return->rejected_at?->toISOString(),
            'received_at' => $return->received_at?->toISOString(),
            'inspection_started_at' => $return->inspection_started_at?->toISOString(),
            'completed_at' => $return->completed_at?->toISOString(),
            'cancelled_at' => $return->cancelled_at?->toISOString(),
            'order' => [
                'id' => $return->order->getKey(), 'order_number' => $return->order->order_number,
                'customer_id' => $return->order->customer_id,
                'customer_name' => $return->order->customer_name ?: 'Guest',
                'email' => $return->order->email, 'phone' => $return->order->phone,
            ],
            'items' => $return->items->map(function (ReturnItem $item) use ($eligibility, $tenants): array {
                $fulfilled = $eligibility->fulfilledQuantity($tenants->get(), $item->orderItem);

                return [
                    'id' => $item->getKey(), 'order_item_id' => $item->order_item_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->orderItem->product_name,
                    'variant_name' => $item->orderItem->variant_name, 'sku' => $item->orderItem->sku,
                    'ordered_quantity' => $item->orderItem->quantity, 'fulfilled_quantity' => $fulfilled,
                    'returnable_quantity' => $eligibility->returnableQuantity(
                        $tenants->get(), $item->orderItem, $item->getKey(),
                    ),
                    'requested_quantity' => $item->requested_quantity,
                    'received_quantity' => $item->received_quantity,
                    'accepted_quantity' => $item->accepted_quantity,
                    'restocked_quantity' => $item->restocked_quantity, 'reason' => $item->reason,
                    'condition' => $item->condition?->value, 'disposition' => $item->disposition?->value,
                    'inspection_notes' => $item->inspection_notes,
                    'inspected_at' => $item->inspected_at?->toISOString(),
                ];
            })->values(),
            'history' => $return->history->map(fn (ReturnStatusHistory $entry): array => [
                'id' => $entry->getKey(), 'from_status' => $entry->from_status?->value,
                'to_status' => $entry->to_status->value, 'reason' => $entry->reason,
                'actor' => $entry->actor?->name, 'created_at' => $entry->created_at?->toISOString(),
            ])->values(),
        ];
    }

    private function refund(Refund $refund): array
    {
        return [
            'id' => $refund->getKey(), 'status' => $refund->status->value,
            'amount' => $refund->amount, 'currency' => $refund->currency, 'reason' => $refund->reason,
            'completed_at' => $refund->completed_at?->toISOString(),
            'created_at' => $refund->created_at?->toISOString(),
        ];
    }

    private function exchange(Exchange $exchange): array
    {
        return [
            'id' => $exchange->getKey(), 'short_id' => substr((string) $exchange->getKey(), 0, 8),
            'status' => $exchange->status->value, 'item_count' => $exchange->items->count(),
            'approved_at' => $exchange->approved_at?->toISOString(),
            'completed_at' => $exchange->completed_at?->toISOString(),
            'cancelled_at' => $exchange->cancelled_at?->toISOString(),
            'created_at' => $exchange->created_at?->toISOString(),
        ];
    }
}
