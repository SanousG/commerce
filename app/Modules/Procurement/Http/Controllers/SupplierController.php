<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Procurement\Application\ArchiveSupplier;
use App\Modules\Procurement\Application\CreateSupplier;
use App\Modules\Procurement\Application\UpdateSupplier;
use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Procurement\Http\Requests\ArchiveSupplierRequest;
use App\Modules\Procurement\Http\Requests\CreateSupplierRequest;
use App\Modules\Procurement\Http\Requests\UpdateSupplierRequest;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request, TenantContext $tenants): Response
    {
        $search = trim((string) $request->query('search'));
        $status = collect(SupplierStatus::cases())->firstWhere('value', $request->query('status'))?->value ?? '';
        $suppliers = Supplier::query()->where('tenant_id', $tenants->id())
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%")))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->withCount('products')->orderBy('name')->paginate(20)->withQueryString()
            ->through(fn (Supplier $supplier): array => [
                'id' => $supplier->getKey(), 'name' => $supplier->name, 'code' => $supplier->code,
                'contact_name' => $supplier->contact_name, 'email' => $supplier->email,
                'phone' => $supplier->phone, 'status' => $supplier->status->value,
                'products_count' => $supplier->products_count,
            ]);

        return Inertia::render('admin/suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => array_column(SupplierStatus::cases(), 'value'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/suppliers/Create');
    }

    public function store(CreateSupplierRequest $request, TenantContext $tenants, CreateSupplier $action): RedirectResponse
    {
        $data = $request->validated();
        $supplier = $action->handle(
            $tenants->get(), $data['code'], $data['name'], $data['contact_name'], $data['email'],
            $data['phone'], $data['website'], $data['notes'],
        );

        return to_route('admin.suppliers.edit', $supplier)->with('status', 'Supplier created.');
    }

    public function edit(TenantContext $tenants, string $supplier): Response
    {
        $scoped = $this->supplier($tenants, $supplier);
        $products = $scoped->products()->with('variant.product')->orderByDesc('is_preferred')->orderBy('created_at')->get();
        $configuredVariantIds = $products->pluck('product_variant_id');
        $variants = ProductVariant::query()->where('tenant_id', $tenants->id())
            ->where('status', ProductVariantStatus::Active)->whereNotIn('id', $configuredVariantIds)
            ->with('product:id,name')->orderBy('sku')->get();

        return Inertia::render('admin/suppliers/Edit', [
            'supplier' => $this->serializeSupplier($scoped),
            'products' => $products->map(fn (SupplierProduct $product): array => $this->serializeProduct($product)),
            'supplierProducts' => $products->map(fn (SupplierProduct $product): array => [
                ...$this->serializeProduct($product),
                'variant' => [
                    'id' => $product->variant->getKey(), 'name' => $product->variant->name,
                    'sku' => $product->variant->sku,
                    'product' => ['id' => $product->variant->product->getKey(), 'name' => $product->variant->product->name],
                ],
            ]),
            'variants' => $variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->getKey(), 'product_name' => $variant->product->name,
                'name' => $variant->name, 'sku' => $variant->sku,
            ]),
            'statuses' => [SupplierStatus::Active->value, SupplierStatus::Inactive->value],
        ]);
    }

    public function update(UpdateSupplierRequest $request, TenantContext $tenants, UpdateSupplier $action, string $supplier): RedirectResponse
    {
        $data = $request->validated();
        $action->handle(
            $tenants->get(), $this->supplier($tenants, $supplier), $data['code'], $data['name'],
            SupplierStatus::from($data['status']), $data['contact_name'], $data['email'], $data['phone'],
            $data['website'], $data['notes'],
        );

        return back()->with('status', 'Supplier updated.');
    }

    public function archive(ArchiveSupplierRequest $request, TenantContext $tenants, ArchiveSupplier $action, string $supplier): RedirectResponse
    {
        $action->handle($tenants->get(), $this->supplier($tenants, $supplier));

        return to_route('admin.suppliers.index')->with('status', 'Supplier archived.');
    }

    private function supplier(TenantContext $tenants, string $id): Supplier
    {
        return Supplier::query()->where('tenant_id', $tenants->id())->findOrFail($id);
    }

    private function serializeSupplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->getKey(), 'name' => $supplier->name, 'code' => $supplier->code,
            'contact_name' => $supplier->contact_name, 'email' => $supplier->email,
            'phone' => $supplier->phone, 'website' => $supplier->website,
            'notes' => $supplier->notes, 'status' => $supplier->status->value,
        ];
    }

    private function serializeProduct(SupplierProduct $product): array
    {
        return [
            'id' => $product->getKey(), 'product_variant_id' => $product->product_variant_id,
            'product_name' => $product->variant->product->name, 'variant_name' => $product->variant->name,
            'sku' => $product->variant->sku, 'supplier_sku' => $product->supplier_sku,
            'unit_cost' => $product->unit_cost, 'currency' => $product->currency,
            'minimum_order_quantity' => $product->minimum_order_quantity, 'order_multiple' => $product->order_multiple,
            'lead_time_days' => $product->lead_time_days, 'is_preferred' => $product->is_preferred,
            'notes' => $product->notes, 'status' => $product->status->value,
        ];
    }
}
