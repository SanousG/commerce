<?php

namespace App\Modules\Returns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Returns\Application\InspectReturnItem;
use App\Modules\Returns\Application\RestockReturnItem;
use App\Modules\Returns\Domain\Enums\ReturnItemCondition;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Models\ReturnItem;
use App\Modules\Returns\Domain\Models\ReturnRequest;
use App\Modules\Returns\Http\Requests\InspectReturnItemRequest;
use App\Modules\Returns\Http\Requests\RestockReturnItemRequest;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class ReturnItemController extends Controller
{
    public function inspect(
        InspectReturnItemRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        InspectReturnItem $action,
        string $item,
    ): RedirectResponse {
        $scopedItem = $this->item($tenants, $stores, $item);
        $scopedReturn = $this->returnRequest($tenants, $stores, $scopedItem->return_id);
        $action->handle(
            $tenants->get(), $stores->get(), $scopedReturn, $scopedItem,
            ReturnItemCondition::from($request->validated('condition')),
            ReturnItemDisposition::from($request->validated('disposition')),
            $request->integer('accepted_quantity'), $request->validated('notes'), $request->user(),
        );

        return back()->with('status', 'Return item inspected.');
    }

    public function restock(
        RestockReturnItemRequest $request,
        TenantContext $tenants,
        StoreContext $stores,
        RestockReturnItem $action,
        string $item,
    ): RedirectResponse {
        $scopedItem = $this->item($tenants, $stores, $item);
        $scopedReturn = $this->returnRequest($tenants, $stores, $scopedItem->return_id);
        $location = $tenants->get()->inventoryLocations()->findOrFail($request->validated('inventory_location_id'));
        $action->handle(
            $tenants->get(), $stores->get(), $scopedReturn, $scopedItem, $location,
            $request->integer('quantity'), $request->user(),
        );

        return back()->with('status', 'Return item restocked.');
    }

    private function returnRequest(TenantContext $tenants, StoreContext $stores, string $id): ReturnRequest
    {
        return ReturnRequest::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }

    private function item(
        TenantContext $tenants,
        StoreContext $stores,
        string $id,
    ): ReturnItem {
        return ReturnItem::query()->where('tenant_id', $tenants->id())
            ->where('store_id', $stores->id())->findOrFail($id);
    }
}
