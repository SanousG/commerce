<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\CreateInventoryLocation;
use App\Modules\Inventory\Application\InventoryAccess;
use App\Modules\Inventory\Application\MakeDefaultInventoryLocation;
use App\Modules\Inventory\Application\UpdateInventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Http\Requests\CreateInventoryLocationRequest;
use App\Modules\Inventory\Http\Requests\UpdateInventoryLocationRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryLocationController extends Controller
{
    public function index(Request $request, TenantContext $context, InventoryAccess $access): Response
    {
        abort_unless($access->canView($request->user()), 403);
        $locations = $context->get()->inventoryLocations()
            ->orderByDesc('is_default')->orderBy('name')->get()
            ->map(fn (InventoryLocation $location): array => $this->data($location));

        return Inertia::render('admin/inventory/locations/Index', ['locations' => $locations]);
    }

    public function create(Request $request, InventoryAccess $access): Response
    {
        abort_unless($access->canAdjust($request->user()), 403);

        return Inertia::render('admin/inventory/locations/Create');
    }

    public function store(
        CreateInventoryLocationRequest $request,
        TenantContext $context,
        CreateInventoryLocation $action,
    ): RedirectResponse {
        $action->handle($context->get(), $request->validated());

        return to_route('admin.inventory.locations.index')->with('status', 'Inventory location created.');
    }

    public function edit(
        Request $request,
        TenantContext $context,
        InventoryAccess $access,
        string $location,
    ): Response {
        abort_unless($access->canAdjust($request->user()), 403);
        $scopedLocation = $context->get()->inventoryLocations()->findOrFail($location);

        return Inertia::render('admin/inventory/locations/Edit', [
            'location' => $this->data($scopedLocation),
        ]);
    }

    public function update(
        UpdateInventoryLocationRequest $request,
        TenantContext $context,
        UpdateInventoryLocation $action,
        string $location,
    ): RedirectResponse {
        $scopedLocation = $context->get()->inventoryLocations()->findOrFail($location);
        $action->handle($context->get(), $scopedLocation, $request->validated());

        return back()->with('status', 'Inventory location updated.');
    }

    public function makeDefault(
        Request $request,
        TenantContext $context,
        InventoryAccess $access,
        MakeDefaultInventoryLocation $action,
        string $location,
    ): RedirectResponse {
        abort_unless($access->canAdjust($request->user()), 403);
        $scopedLocation = $context->get()->inventoryLocations()->findOrFail($location);
        $action->handle($context->get(), $scopedLocation);

        return back()->with('status', 'Default inventory location changed.');
    }

    private function data(InventoryLocation $location): array
    {
        return [
            'id' => $location->getKey(),
            'name' => $location->name,
            'code' => $location->code,
            'status' => $location->status->value,
            'is_default' => $location->is_default,
        ];
    }
}
