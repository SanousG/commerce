<?php

namespace App\Modules\Costing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Costing\Application\InitializeInventoryCost;
use App\Modules\Costing\Http\Requests\InitializeInventoryCostRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCostController extends Controller
{
    public function index(Request $request, TenantContext $tenants): Response
    {
        $tenantId = $tenants->id();
        $search = trim((string) $request->query('search'));
        $rows = DB::table('inventory_levels as il')
            ->join('inventory_locations as loc', function ($join): void {
                $join->on('loc.id', '=', 'il.inventory_location_id')->on('loc.tenant_id', '=', 'il.tenant_id');
            })
            ->join('product_variants as pv', function ($join): void {
                $join->on('pv.id', '=', 'il.product_variant_id')->on('pv.tenant_id', '=', 'il.tenant_id');
            })
            ->join('products as p', function ($join): void {
                $join->on('p.id', '=', 'pv.product_id')->on('p.tenant_id', '=', 'il.tenant_id');
            })
            ->leftJoin('inventory_valuations as iv', function ($join): void {
                $join->on('iv.inventory_location_id', '=', 'il.inventory_location_id')
                    ->on('iv.product_variant_id', '=', 'il.product_variant_id')
                    ->on('iv.tenant_id', '=', 'il.tenant_id');
            })
            ->where('il.tenant_id', $tenantId)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('pv.sku', 'ilike', "%{$search}%")
                ->orWhere('p.name', 'ilike', "%{$search}%")
                ->orWhere('loc.name', 'ilike', "%{$search}%")))
            ->orderBy('p.name')->orderBy('pv.sku')->orderBy('loc.name')
            ->paginate(30, [
                'il.inventory_location_id', 'il.product_variant_id', 'il.on_hand',
                'loc.name as location_name', 'loc.code as location_code', 'p.name as product_name',
                'pv.name as variant_name', 'pv.sku', 'iv.average_unit_cost', 'iv.currency',
            ])->withQueryString();
        $rows->through(fn ($row): array => [
            'location_id' => $row->inventory_location_id,
            'variant_id' => $row->product_variant_id,
            'location_name' => $row->location_name,
            'location_code' => $row->location_code,
            'product_name' => $row->product_name,
            'variant_name' => $row->variant_name,
            'sku' => $row->sku,
            'on_hand' => (int) $row->on_hand,
            'status' => $row->average_unit_cost === null ? 'UNINITIALIZED' : 'ACTIVE',
            'average_unit_cost' => $row->average_unit_cost,
            'inventory_value' => $row->average_unit_cost === null ? null : (string) BigDecimal::of($row->average_unit_cost)
                ->multipliedBy($row->on_hand)->toScale(4, RoundingMode::HalfUp),
            'currency' => $row->currency,
        ]);
        $totals = DB::table('inventory_valuations as iv')
            ->join('inventory_levels as il', function ($join): void {
                $join->on('il.inventory_location_id', '=', 'iv.inventory_location_id')
                    ->on('il.product_variant_id', '=', 'iv.product_variant_id')
                    ->on('il.tenant_id', '=', 'iv.tenant_id');
            })
            ->where('iv.tenant_id', $tenantId)->whereNotNull('iv.average_unit_cost')
            ->groupBy('iv.currency')->orderBy('iv.currency')
            ->selectRaw('iv.currency, ROUND(SUM(il.on_hand * iv.average_unit_cost), 4) AS inventory_value')
            ->get()->map(fn ($row): array => ['currency' => $row->currency, 'inventory_value' => $row->inventory_value]);

        return Inertia::render('admin/inventory/Valuation', [
            'valuations' => $rows,
            'totals' => $totals,
            'filters' => ['search' => $search],
        ]);
    }

    public function initialize(
        InitializeInventoryCostRequest $request,
        TenantContext $tenants,
        InitializeInventoryCost $action,
        string $location,
        string $variant,
    ): RedirectResponse {
        $tenant = $tenants->get();
        $scopedLocation = $tenant->inventoryLocations()->findOrFail($location);
        $scopedVariant = ProductVariant::query()->where('tenant_id', $tenant->getKey())->findOrFail($variant);
        $action->handle(
            $tenant,
            $scopedLocation,
            $scopedVariant,
            $request->validated('unit_cost'),
            $request->validated('currency'),
            $request->user(),
        );

        return back()->with('status', 'Inventory cost initialized.');
    }
}
