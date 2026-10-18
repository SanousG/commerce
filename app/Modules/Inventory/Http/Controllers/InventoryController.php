<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Costing\Domain\Models\InventoryValuation;
use App\Modules\Inventory\Application\AdjustInventory;
use App\Modules\Inventory\Application\InventoryAccess;
use App\Modules\Inventory\Application\TransferInventory;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryMovement;
use App\Modules\Inventory\Http\Requests\AdjustInventoryRequest;
use App\Modules\Inventory\Http\Requests\TransferInventoryRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, TenantContext $context, InventoryAccess $access): Response
    {
        abort_unless($access->canView($request->user()), 403);
        $tenant = $context->get();
        $search = trim((string) $request->query('search'));
        $variants = ProductVariant::query()
            ->where('tenant_id', $tenant->getKey())
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('sku', 'ilike', "%{$search}%")
                    ->orWhereHas('product', fn ($products) => $products->where('name', 'ilike', "%{$search}%"));
            }))
            ->with('product:id,name')
            ->withSum('inventoryLevels as on_hand', 'on_hand')
            ->withSum('inventoryLevels as reserved', 'reserved')
            ->withCount([
                'inventoryLevels as locations_with_stock' => fn ($query) => $query->where('on_hand', '>', 0),
            ])
            ->orderBy('sku')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProductVariant $variant): array => [
                'product_name' => $variant->product->name,
                'variant_id' => $variant->getKey(),
                'sku' => $variant->sku,
                'on_hand' => (int) ($variant->on_hand ?? 0),
                'reserved' => (int) ($variant->reserved ?? 0),
                'available' => (int) ($variant->on_hand ?? 0) - (int) ($variant->reserved ?? 0),
                'locations_with_stock' => $variant->locations_with_stock,
            ]);

        return Inertia::render('admin/inventory/Index', [
            'variants' => $variants,
            'filters' => ['search' => $search, 'can_adjust' => $access->canAdjust($request->user())],
        ]);
    }

    public function showVariant(
        Request $request,
        TenantContext $context,
        InventoryAccess $access,
        string $variant,
    ): Response {
        abort_unless($access->canView($request->user()), 403);
        $tenant = $context->get();
        $canViewCost = $request->user()->can('inventory.cost.view');
        $scopedVariant = ProductVariant::query()->where('tenant_id', $tenant->getKey())
            ->with('product:id,name')->findOrFail($variant);
        $valuations = $canViewCost ? InventoryValuation::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('product_variant_id', $scopedVariant->getKey())
            ->get()->keyBy('inventory_location_id') : collect();
        $levels = InventoryLevel::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('product_variant_id', $scopedVariant->getKey())
            ->with('location:id,name,code')
            ->get()
            ->sortBy(fn (InventoryLevel $level): string => $level->location->name)
            ->values()
            ->map(function (InventoryLevel $level) use ($canViewCost, $valuations): array {
                $data = [
                    'location_id' => $level->inventory_location_id,
                    'location_name' => $level->location->name,
                    'location_code' => $level->location->code,
                    'on_hand' => $level->on_hand,
                    'reserved' => $level->reserved,
                    'available' => $level->available,
                ];
                if ($canViewCost) {
                    $valuation = $valuations->get($level->inventory_location_id);
                    $average = $valuation?->average_unit_cost;
                    $data['cost_status'] = $average === null ? 'UNINITIALIZED' : 'ACTIVE';
                    $data['average_unit_cost'] = $average;
                    $data['inventory_value'] = $average === null ? null : (string) BigDecimal::of($average)
                        ->multipliedBy($level->on_hand)->toScale(4, RoundingMode::HalfUp);
                    $data['cost_currency'] = $valuation?->currency;
                }

                return $data;
            });
        $movements = InventoryMovement::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('product_variant_id', $scopedVariant->getKey())
            ->with('location:id,name')
            ->latest('occurred_at')
            ->paginate(50, ['*'], 'movements_page')
            ->withQueryString()
            ->through(fn (InventoryMovement $movement): array => [
                'id' => $movement->getKey(),
                'location_name' => $movement->location->name,
                'type' => $movement->type->value,
                'quantity_delta' => $movement->quantity_delta,
                'reason' => $movement->reason,
                'occurred_at' => $movement->occurred_at->toDateTimeString(),
            ]);
        $locations = $tenant->inventoryLocations()
            ->where('status', InventoryLocationStatus::Active)
            ->orderByDesc('is_default')->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('admin/inventory/Variant', [
            'product' => ['id' => $scopedVariant->product->getKey(), 'name' => $scopedVariant->product->name],
            'variant' => [
                'id' => $scopedVariant->getKey(),
                'name' => $scopedVariant->name,
                'sku' => $scopedVariant->sku,
            ],
            'levels' => $levels,
            'movements' => $movements,
            'locations' => $locations,
            'can_adjust' => $access->canAdjust($request->user()),
            'can_view_cost' => $canViewCost,
            'can_manage_cost' => $request->user()->can('inventory.cost.manage'),
        ]);
    }

    public function adjust(
        AdjustInventoryRequest $request,
        TenantContext $context,
        AdjustInventory $action,
        string $location,
        string $variant,
    ): RedirectResponse {
        $tenant = $context->get();
        $scopedLocation = $tenant->inventoryLocations()->findOrFail($location);
        $scopedVariant = ProductVariant::query()->where('tenant_id', $tenant->getKey())->findOrFail($variant);
        $action->handle(
            $tenant,
            $scopedLocation,
            $scopedVariant,
            $request->integer('quantity_delta'),
            $request->user(),
            $request->validated('reason'),
        );

        return back()->with('status', 'Inventory adjustment recorded.');
    }

    public function createTransfer(Request $request, TenantContext $context, InventoryAccess $access): Response
    {
        abort_unless($access->canAdjust($request->user()), 403);
        $tenant = $context->get();
        $locations = $tenant->inventoryLocations()
            ->where('status', InventoryLocationStatus::Active)
            ->orderByDesc('is_default')->orderBy('name')
            ->get(['id', 'name', 'code']);
        $variants = ProductVariant::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', ProductVariantStatus::Active)
            ->with('product:id,name')
            ->orderBy('sku')
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->getKey(),
                'product_name' => $variant->product->name,
                'name' => $variant->name,
                'sku' => $variant->sku,
            ]);

        return Inertia::render('admin/inventory/transfers/Create', [
            'locations' => $locations,
            'variants' => $variants,
        ]);
    }

    public function transfer(
        TransferInventoryRequest $request,
        TenantContext $context,
        TransferInventory $action,
    ): RedirectResponse {
        $tenant = $context->get();
        $source = $tenant->inventoryLocations()->findOrFail($request->validated('source_location_id'));
        $destination = $tenant->inventoryLocations()->findOrFail($request->validated('destination_location_id'));
        /** @var list<array{product_variant_id: string, quantity: int}> $lines */
        $lines = $request->validated('lines');
        $action->handle(
            $tenant,
            $source,
            $destination,
            $lines,
            $request->user(),
            $request->validated('notes'),
        );

        return to_route('admin.inventory.index')->with('status', 'Inventory transfer completed.');
    }
}
