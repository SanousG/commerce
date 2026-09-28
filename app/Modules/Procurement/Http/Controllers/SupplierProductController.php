<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Procurement\Application\ArchiveSupplierProduct;
use App\Modules\Procurement\Application\CreateSupplierProduct;
use App\Modules\Procurement\Application\UpdateSupplierProduct;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Procurement\Http\Requests\ArchiveSupplierRequest;
use App\Modules\Procurement\Http\Requests\SupplierProductRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

class SupplierProductController extends Controller
{
    public function store(SupplierProductRequest $request, TenantContext $tenants, CreateSupplierProduct $action, string $supplier): RedirectResponse
    {
        $data = $request->validated();
        $scopedSupplier = Supplier::query()->where('tenant_id', $tenants->id())->findOrFail($supplier);
        $variant = ProductVariant::query()->where('tenant_id', $tenants->id())->findOrFail($data['product_variant_id']);
        $action->handle(
            $tenants->get(), $scopedSupplier, $variant, $data['supplier_sku'], $data['unit_cost'], $data['currency'],
            $data['minimum_order_quantity'], $data['order_multiple'] ?? null, $data['lead_time_days'] ?? null,
            $data['is_preferred'] ?? false, $data['notes'] ?? null,
        );

        return back()->with('status', 'Supplier product added.');
    }

    public function update(SupplierProductRequest $request, TenantContext $tenants, UpdateSupplierProduct $action, string $supplierProduct): RedirectResponse
    {
        $data = $request->validated();
        $product = $this->product($tenants, $supplierProduct);
        $action->handle(
            $tenants->get(), $product,
            array_key_exists('supplier_sku', $data) ? $data['supplier_sku'] : $product->supplier_sku,
            $data['unit_cost'], $data['currency'], $data['minimum_order_quantity'],
            array_key_exists('order_multiple', $data) ? $data['order_multiple'] : $product->order_multiple,
            SupplierProductStatus::from($data['status'] ?? $product->status->value),
            array_key_exists('lead_time_days', $data) ? $data['lead_time_days'] : $product->lead_time_days,
            $data['is_preferred'] ?? $product->is_preferred,
            array_key_exists('notes', $data) ? $data['notes'] : $product->notes,
        );

        return back()->with('status', 'Supplier product updated.');
    }

    public function archive(ArchiveSupplierRequest $request, TenantContext $tenants, ArchiveSupplierProduct $action, string $supplierProduct): RedirectResponse
    {
        $action->handle($tenants->get(), $this->product($tenants, $supplierProduct));

        return back()->with('status', 'Supplier product archived.');
    }

    private function product(TenantContext $tenants, string $id): SupplierProduct
    {
        return SupplierProduct::query()->where('tenant_id', $tenants->id())->findOrFail($id);
    }
}
