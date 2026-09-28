<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class ArchiveSupplierProduct
{
    public function handle(Tenant $tenant, SupplierProduct $product): SupplierProduct
    {
        return DB::transaction(function () use ($tenant, $product): SupplierProduct {
            $product = SupplierProduct::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($product->getKey());
            $product->update(['status' => SupplierProductStatus::Archived, 'is_preferred' => false]);

            return $product->refresh();
        });
    }
}
