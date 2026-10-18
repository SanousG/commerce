<?php

namespace App\Modules\Fulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fulfillment\Application\CancelFulfillment;
use App\Modules\Fulfillment\Application\CompleteFulfillment;
use App\Modules\Fulfillment\Application\CreateFulfillment;
use App\Modules\Fulfillment\Application\FulfillmentPaymentEligibility;
use App\Modules\Fulfillment\Application\StartFulfillment;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Fulfillment\Domain\Models\FulfillmentStatusHistory;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Fulfillment\Domain\Models\ShipmentStatusHistory;
use App\Modules\Fulfillment\Http\Requests\CancelFulfillmentRequest;
use App\Modules\Fulfillment\Http\Requests\CreateFulfillmentRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FulfillmentController extends Controller
{
    public function index(TenantContext $tenants, StoreContext $stores): Response
    {
        $fulfillments = Fulfillment::query()
            ->where('tenant_id', $tenants->id())->where('store_id', $stores->id())
            ->with(['order:id,order_number', 'location:id,name,code', 'shipment:id,fulfillment_id,status'])
            ->withCount('items')->withSum('items as total_quantity', 'quantity')
            ->orderByDesc('created_at')->paginate(20)->withQueryString();
        $fulfillments->through(fn (Fulfillment $fulfillment): array => [
            'id' => $fulfillment->getKey(), 'order_id' => $fulfillment->order_id,
            'order_number' => $fulfillment->order->order_number, 'location_name' => $fulfillment->location->name,
            'location_code' => $fulfillment->location->code, 'status' => $fulfillment->status->value,
            'item_count' => $fulfillment->items_count, 'total_quantity' => (int) $fulfillment->total_quantity,
            'shipment_statuses' => $fulfillment->shipment === null ? [] : [$fulfillment->shipment->status->value],
            'created_at' => $fulfillment->created_at?->toISOString(),
        ]);

        return Inertia::render('admin/fulfillments/Index', ['fulfillments' => $fulfillments]);
    }

    public function show(
        Request $request,
        TenantContext $tenants,
        StoreContext $stores,
        FulfillmentPaymentEligibility $paymentEligibility,
        string $fulfillment,
    ): Response {
        $scoped = $this->fulfillment($tenants, $stores, $fulfillment);
        $scoped->load([
            'order', 'location:id,name,code', 'items.orderItem',
            'history' => fn ($query) => $query->with('actor')->orderBy('created_at'),
            'shipment.history' => fn ($query) => $query->with('actor')->orderBy('created_at'),
        ]);
        $eligibility = $paymentEligibility->evaluate($scoped->order);
        $canManage = $request->user()->can('fulfillments.manage');
        $canComplete = $request->user()->can('fulfillments.complete');
        $canManageShipments = $request->user()->can('shipments.manage');

        return Inertia::render('admin/fulfillments/Show', [
            'fulfillment' => $this->serialize($scoped, $canManageShipments, $request->user()->can('inventory.cost.view')),
            'paymentEligibility' => $eligibility,
            'capabilities' => [
                'manage' => $canManage, 'complete' => $canComplete, 'shipments_manage' => $canManageShipments,
                'start' => $canManage && $scoped->status === FulfillmentStatus::Pending,
                'cancel' => $canManage && in_array($scoped->status, [FulfillmentStatus::Pending, FulfillmentStatus::Processing], true),
                'complete_action' => $canComplete && $scoped->status === FulfillmentStatus::Processing,
                'create_shipment' => $canManageShipments && $scoped->shipment === null
                    && $scoped->status !== FulfillmentStatus::Cancelled && $scoped->order->delivery_type === 'SHIPPING',
            ],
        ]);
    }

    public function store(
        CreateFulfillmentRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CreateFulfillment $action,
        string $order,
    ): RedirectResponse {
        $scopedOrder = $stores->get()->orders()->where('tenant_id', $tenants->id())->findOrFail($order);
        $location = $tenants->get()->inventoryLocations()->findOrFail($request->validated('inventory_location_id'));
        $scopedOrder->load('items');
        $reservationIds = collect($request->validated('items'))->pluck('inventory_reservation_id');
        $reservations = $tenants->get()->inventoryReservations()->whereIn('id', $reservationIds)->get()->keyBy('id');
        $allocations = collect($request->validated('items'))->map(function (array $item) use ($reservations, $scopedOrder): array {
            $reservation = $reservations->get($item['inventory_reservation_id']);
            abort_if($reservation === null, 404);
            $orderItem = $scopedOrder->items->firstWhere('checkout_line_id', $reservation->reference_id);
            abort_if($orderItem === null, 404);

            return [
                'order_item_id' => $orderItem->getKey(),
                'inventory_reservation_id' => $item['inventory_reservation_id'],
                'quantity' => $item['quantity'],
            ];
        })->all();
        $action->handle($tenants->get(), $scopedOrder, $location, $allocations, $request->user());

        return back()->with('status', 'Fulfillment created.');
    }

    public function start(Request $request, TenantContext $tenants, StoreContext $stores, StartFulfillment $action, string $fulfillment): RedirectResponse
    {
        $action->handle($tenants->get(), $this->fulfillment($tenants, $stores, $fulfillment), $request->user());

        return back()->with('status', 'Fulfillment started.');
    }

    public function complete(Request $request, TenantContext $tenants, StoreContext $stores, CompleteFulfillment $action, string $fulfillment): RedirectResponse
    {
        $action->handle($tenants->get(), $this->fulfillment($tenants, $stores, $fulfillment), $request->user());

        return back()->with('status', 'Fulfillment completed and inventory committed.');
    }

    public function cancel(CancelFulfillmentRequest $request, TenantContext $tenants, StoreContext $stores, CancelFulfillment $action, string $fulfillment): RedirectResponse
    {
        $action->handle(
            $tenants->get(), $this->fulfillment($tenants, $stores, $fulfillment),
            $request->validated('reason'), $request->user(),
        );

        return back()->with('status', 'Fulfillment cancelled.');
    }

    private function fulfillment(TenantContext $tenants, StoreContext $stores, string $id): Fulfillment
    {
        return Fulfillment::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }

    private function serialize(Fulfillment $fulfillment, bool $canManageShipments, bool $canViewCost): array
    {
        $shipment = $fulfillment->shipment;

        return [
            'id' => $fulfillment->getKey(), 'order_id' => $fulfillment->order_id,
            'order_number' => $fulfillment->order->order_number, 'status' => $fulfillment->status->value,
            'location_name' => $fulfillment->location->name, 'location_code' => $fulfillment->location->code,
            'started_at' => $fulfillment->started_at?->toISOString(),
            'completed_at' => $fulfillment->fulfilled_at?->toISOString(),
            'cancelled_at' => $fulfillment->cancelled_at?->toISOString(),
            'cancellation_reason' => $fulfillment->cancellation_reason,
            'created_at' => $fulfillment->created_at?->toISOString(),
            'items' => $fulfillment->items->map(function ($item) use ($canViewCost): array {
                $data = [
                    'id' => $item->getKey(), 'product_name' => $item->orderItem->product_name,
                    'variant_name' => $item->orderItem->variant_name, 'sku' => $item->orderItem->sku,
                    'quantity' => $item->quantity, 'inventory_reservation_id' => $item->inventory_reservation_id,
                ];
                if ($canViewCost) {
                    $data['unit_cost_snapshot'] = $item->unit_cost_snapshot;
                    $data['cogs_amount'] = $item->cogs_amount;
                    $data['cost_currency'] = $item->cost_currency;
                }

                return $data;
            })->values(),
            'history' => $fulfillment->history->map(fn (FulfillmentStatusHistory $entry): array => $this->history($entry))->values(),
            'shipments' => $shipment === null ? [] : [
                $this->shipment($shipment, $canManageShipments, $fulfillment->status === FulfillmentStatus::Fulfilled),
            ],
        ];
    }

    private function shipment(Shipment $shipment, bool $canManage, bool $canLeaveWarehouse): array
    {
        return [
            'id' => $shipment->getKey(), 'status' => $shipment->status->value,
            'carrier' => $shipment->carrier_name, 'carrier_name' => $shipment->carrier_name,
            'service_name' => $shipment->service_name,
            'tracking_number' => $shipment->tracking_number, 'tracking_url' => $shipment->tracking_url,
            'ready_at' => $shipment->ready_at?->toISOString(), 'shipped_at' => $shipment->shipped_at?->toISOString(),
            'delivered_at' => $shipment->delivered_at?->toISOString(),
            'can_ready' => $canManage && $shipment->status === ShipmentStatus::Pending,
            'can_ship' => $canManage && $canLeaveWarehouse && $shipment->status === ShipmentStatus::Ready,
            'can_deliver' => $canManage && $shipment->status === ShipmentStatus::Shipped,
            'can_cancel' => $canManage && in_array($shipment->status, [ShipmentStatus::Pending, ShipmentStatus::Ready], true),
            'history' => $shipment->history->map(fn (ShipmentStatusHistory $entry): array => $this->history($entry))->values(),
        ];
    }

    private function history(FulfillmentStatusHistory|ShipmentStatusHistory $entry): array
    {
        return [
            'id' => $entry->getKey(), 'from_status' => $entry->from_status?->value,
            'to_status' => $entry->to_status->value, 'reason' => $entry->reason,
            'actor' => $entry->actor?->name, 'created_at' => $entry->created_at?->toISOString(),
        ];
    }
}
