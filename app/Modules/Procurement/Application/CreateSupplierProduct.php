<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSupplierProduct
{
    public function handle(Tenant $tenant, Supplier $supplier, ProductVariant $variant, ?string $supplierSku, string|int|float $unitCost, string $currency, int $minimumOrderQuantity = 1, ?int $orderMultiple = null, ?int $leadTimeDays = null, bool $isPreferred = false, ?string $notes = null): SupplierProduct
    {
        if ($minimumOrderQuantity <= 0 || ($orderMultiple !== null && $orderMultiple <= 0)) {
            throw ValidationException::withMessages(['quantity' => 'MOQ must be positive and the order multiple must be null or positive.']);
        }

        return DB::transaction(function () use ($tenant, $supplier, $variant, $supplierSku, $unitCost, $currency, $minimumOrderQuantity, $orderMultiple, $leadTimeDays, $isPreferred, $notes): SupplierProduct {
            $supplier = Supplier::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($supplier->getKey());
            $variant = ProductVariant::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($variant->getKey());
            if ($supplier->status === SupplierStatus::Archived) {
                throw ValidationException::withMessages(['supplier_id' => 'An archived supplier cannot provide products.']);
            }
            if ($isPreferred) {
                SupplierProduct::query()->where('tenant_id', $tenant->getKey())->where('product_variant_id', $variant->getKey())
                    ->where('is_preferred', true)->lockForUpdate()->update(['is_preferred' => false]);
            }

            $supplierSku = $supplierSku === null ? null : trim($supplierSku);

            return SupplierProduct::query()->create([
                'tenant_id' => $tenant->getKey(), 'supplier_id' => $supplier->getKey(), 'product_variant_id' => $variant->getKey(),
                'supplier_sku' => $supplierSku === '' ? null : $supplierSku, 'status' => SupplierProductStatus::Active,
                'unit_cost' => ProcurementValues::amount($unitCost), 'currency' => ProcurementValues::currency($currency),
                'minimum_order_quantity' => $minimumOrderQuantity, 'order_multiple' => $orderMultiple,
                'lead_time_days' => $leadTimeDays, 'is_preferred' => $isPreferred, 'notes' => $notes,
            ]);
        });
    }
}
