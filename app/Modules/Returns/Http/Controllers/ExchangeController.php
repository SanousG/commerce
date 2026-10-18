<?php

namespace App\Modules\Returns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Returns\Application\ApproveExchange;
use App\Modules\Returns\Application\CancelExchange;
use App\Modules\Returns\Application\CompleteExchange;
use App\Modules\Returns\Application\CreateExchange;
use App\Modules\Returns\Domain\Enums\ExchangeStatus;
use App\Modules\Returns\Domain\Models\Exchange;
use App\Modules\Returns\Domain\Models\ExchangeItem;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Returns\Http\Requests\CancelExchangeRequest;
use App\Modules\Returns\Http\Requests\CreateExchangeRequest;
use App\Modules\Returns\Http\Requests\ExchangeIndexRequest;
use App\Modules\Returns\Http\Requests\ManageExchangeRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeController extends Controller
{
    public function index(ExchangeIndexRequest $request, TenantContext $tenants, StoreContext $stores): Response
    {
        abort_unless($request->user()->can('exchanges.view'), 403);
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? '';
        $orderId = $filters['order_id'] ?? '';
        $exchanges = Exchange::query()
            ->where('tenant_id', $tenants->id())->where('store_id', $stores->id())
            ->when($search !== '', fn ($query) => $query->whereHas('order', fn ($query) => $query
                ->where('order_number', 'ilike', "%{$search}%")
                ->orWhere('customer_name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($orderId !== '', fn ($query) => $query->where('order_id', $orderId))
            ->with('order:id,order_number,customer_name,email')
            ->withCount('items')->orderByDesc('created_at')->paginate(20)->withQueryString();
        $exchanges->through(fn (Exchange $exchange): array => [
            'id' => $exchange->getKey(), 'short_id' => substr((string) $exchange->getKey(), 0, 8),
            'return_id' => $exchange->return_id, 'order_id' => $exchange->order_id,
            'order_number' => $exchange->order->order_number,
            'customer_name' => $exchange->order->customer_name ?: 'Guest',
            'customer_email' => $exchange->order->email, 'status' => $exchange->status->value,
            'item_count' => $exchange->items_count, 'created_at' => $exchange->created_at?->toISOString(),
        ]);

        return Inertia::render('admin/exchanges/Index', [
            'exchanges' => $exchanges,
            'filters' => ['search' => $search, 'status' => $status, 'order_id' => $orderId],
            'statuses' => array_column(ExchangeStatus::cases(), 'value'),
        ]);
    }

    public function show(
        Request $request,
        TenantContext $tenants,
        StoreContext $stores,
        string $exchange,
    ): Response {
        abort_unless($request->user()->can('exchanges.view'), 403);
        $scoped = $this->exchange($tenants, $stores, $exchange);
        $scoped->load([
            'order', 'returnRequest:id,tenant_id,store_id,order_id,status,requested_at',
            'items.returnItem.orderItem', 'items.variant.product',
        ]);
        $canManage = $request->user()->can('exchanges.manage');

        return Inertia::render('admin/exchanges/Show', [
            'exchange' => $this->serialize($scoped),
            'capabilities' => [
                'manage' => $canManage,
                'approve' => $canManage && $scoped->status === ExchangeStatus::Pending,
                'complete' => $canManage && $scoped->status === ExchangeStatus::Approved,
                'cancel' => $canManage
                    && in_array($scoped->status, [ExchangeStatus::Pending, ExchangeStatus::Approved], true),
            ],
        ]);
    }

    public function store(
        CreateExchangeRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CreateExchange $action,
        string $return,
    ): RedirectResponse {
        /** @var list<array{return_item_id: string, replacement_product_variant_id: string, quantity: int}> $items */
        $items = $request->validated('items');
        $exchange = $action->handle(
            $tenants->get(), $stores->get(), $this->returnRequest($tenants, $stores, $return),
            $items, $request->user(),
        );

        return to_route('admin.exchanges.show', $exchange)->with('status', 'Exchange created.');
    }

    public function approve(
        ManageExchangeRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        ApproveExchange $action,
        string $exchange,
    ): RedirectResponse {
        $action->handle($tenants->get(), $stores->get(), $this->exchange($tenants, $stores, $exchange));

        return back()->with('status', 'Exchange approved.');
    }

    public function complete(
        ManageExchangeRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CompleteExchange $action,
        string $exchange,
    ): RedirectResponse {
        $action->handle($tenants->get(), $stores->get(), $this->exchange($tenants, $stores, $exchange));

        return back()->with('status', 'Exchange completed.');
    }

    public function cancel(
        CancelExchangeRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CancelExchange $action,
        string $exchange,
    ): RedirectResponse {
        $action->handle(
            $tenants->get(), $stores->get(), $this->exchange($tenants, $stores, $exchange),
            $request->validated('reason'),
        );

        return back()->with('status', 'Exchange cancelled.');
    }

    private function returnRequest(TenantContext $tenants, StoreContext $stores, string $id): ReturnRequest
    {
        return ReturnRequest::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }

    private function exchange(TenantContext $tenants, StoreContext $stores, string $id): Exchange
    {
        return Exchange::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }

    private function serialize(Exchange $exchange): array
    {
        return [
            'id' => $exchange->getKey(), 'short_id' => substr((string) $exchange->getKey(), 0, 8),
            'status' => $exchange->status->value,
            'approved_at' => $exchange->approved_at?->toISOString(),
            'completed_at' => $exchange->completed_at?->toISOString(),
            'cancelled_at' => $exchange->cancelled_at?->toISOString(),
            'cancellation_reason' => $exchange->cancellation_reason,
            'created_at' => $exchange->created_at?->toISOString(),
            'order' => [
                'id' => $exchange->order->getKey(), 'order_number' => $exchange->order->order_number,
                'customer_id' => $exchange->order->customer_id,
                'customer_name' => $exchange->order->customer_name ?: 'Guest',
                'email' => $exchange->order->email, 'phone' => $exchange->order->phone,
            ],
            'return' => [
                'id' => $exchange->returnRequest->getKey(),
                'short_id' => substr((string) $exchange->returnRequest->getKey(), 0, 8),
                'status' => $exchange->returnRequest->status->value,
                'requested_at' => $exchange->returnRequest->requested_at?->toISOString(),
            ],
            'items' => $exchange->items->map(fn (ExchangeItem $item): array => [
                'id' => $item->getKey(), 'return_item_id' => $item->return_item_id,
                'quantity' => $item->quantity,
                'returned_item' => [
                    'product_name' => $item->returnItem->orderItem->product_name,
                    'variant_name' => $item->returnItem->orderItem->variant_name,
                    'sku' => $item->returnItem->orderItem->sku,
                ],
                'replacement_variant' => [
                    'id' => $item->variant->getKey(), 'product_name' => $item->variant->product->name,
                    'name' => $item->variant->name, 'sku' => $item->variant->sku,
                ],
            ])->values(),
        ];
    }
}
