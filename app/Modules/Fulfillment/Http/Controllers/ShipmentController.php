<?php

namespace App\Modules\Fulfillment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fulfillment\Application\CancelShipment;
use App\Modules\Fulfillment\Application\CreateShipment;
use App\Modules\Fulfillment\Application\MarkShipmentDelivered;
use App\Modules\Fulfillment\Application\MarkShipmentReady;
use App\Modules\Fulfillment\Application\MarkShipmentShipped;
use App\Modules\Fulfillment\Domain\Models\Fulfillment;
use App\Modules\Fulfillment\Domain\Models\Shipment;
use App\Modules\Fulfillment\Http\Requests\CancelShipmentRequest;
use App\Modules\Fulfillment\Http\Requests\CreateShipmentRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function store(
        CreateShipmentRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        CreateShipment $action,
        string $fulfillment,
    ): RedirectResponse {
        $action->handle(
            $tenants->get(), $this->fulfillment($tenants, $stores, $fulfillment),
            $request->validated('carrier_name'), $request->validated('service_name'),
            $request->validated('tracking_number'),
            $request->validated('tracking_url'), $request->user(),
        );

        return back()->with('status', 'Shipment created.');
    }

    public function ready(Request $request, TenantContext $tenants, StoreContext $stores, MarkShipmentReady $action, string $shipment): RedirectResponse
    {
        $action->handle($tenants->get(), $this->shipment($tenants, $stores, $shipment), $request->user());

        return back()->with('status', 'Shipment marked ready.');
    }

    public function ship(Request $request, TenantContext $tenants, StoreContext $stores, MarkShipmentShipped $action, string $shipment): RedirectResponse
    {
        $action->handle($tenants->get(), $this->shipment($tenants, $stores, $shipment), $request->user());

        return back()->with('status', 'Shipment marked shipped.');
    }

    public function deliver(Request $request, TenantContext $tenants, StoreContext $stores, MarkShipmentDelivered $action, string $shipment): RedirectResponse
    {
        $action->handle($tenants->get(), $this->shipment($tenants, $stores, $shipment), $request->user());

        return back()->with('status', 'Shipment marked delivered.');
    }

    public function cancel(CancelShipmentRequest $request, TenantContext $tenants, StoreContext $stores, CancelShipment $action, string $shipment): RedirectResponse
    {
        $action->handle(
            $tenants->get(), $this->shipment($tenants, $stores, $shipment),
            $request->validated('reason'), $request->user(),
        );

        return back()->with('status', 'Shipment cancelled.');
    }

    private function fulfillment(TenantContext $tenants, StoreContext $stores, string $id): Fulfillment
    {
        return Fulfillment::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }

    private function shipment(TenantContext $tenants, StoreContext $stores, string $id): Shipment
    {
        return Shipment::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }
}
