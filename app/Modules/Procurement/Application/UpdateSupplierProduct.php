<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSupplierProduct
{
    public function handle(Tenant $tenant, SupplierProduct $product, ?string $supplierSku, string|int|float $unitCost, string $currency, int $minimumOrderQuantity, ?int $orderMultiple, SupplierProductStatus $status, ?int $leadTimeDays = null, bool $isPreferred = false, ?string $notes = null): SupplierProduct
    {
        if ($status === SupplierProductStatus::Archived) {
            throw ValidationException::withMessages(['status' => 'Use the supplier-product archive operation.']);
        }
        if ($minimumOrderQuantity <= 0 || ($orderMultiple !== null && $orderMultiple <= 0)) {
            throw ValidationException::withMessages(['quantity' => 'MOQ must be positive and the order multiple must be null or positive.']);
        }
        if ($isPreferred && $status !== SupplierProductStatus::Active) {
            throw ValidationException::withMessages(['is_preferred' => 'Only an active supplier product can be preferred.']);
        }

        return DB::transaction(function () use ($tenant, $product, $supplierSku, $unitCost, $currency, $minimumOrderQuantity, $orderMultiple, $status, $leadTimeDays, $isPreferred, $notes): SupplierProduct {
            $product = SupplierProduct::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($product->getKey());
            if ($isPreferred) {
                SupplierProduct::query()->where('tenant_id', $tenant->getKey())->where('product_variant_id', $product->product_variant_id)
                    ->whereKeyNot($product->getKey())->where('is_preferred', true)->lockForUpdate()->update(['is_preferred' => false]);
            }
            $supplierSku = $supplierSku === null ? null : trim($supplierSku);
            $product->update([
                'supplier_sku' => $supplierSku === '' ? null : $supplierSku,
                'unit_cost' => ProcurementValues::amount($unitCost), 'currency' => ProcurementValues::currency($currency),
                'minimum_order_quantity' => $minimumOrderQuantity, 'order_multiple' => $orderMultiple,
                'lead_time_days' => $leadTimeDays, 'status' => $status, 'is_preferred' => $isPreferred, 'notes' => $notes,
            ]);

            return $product->refresh();
        });
    }
}
