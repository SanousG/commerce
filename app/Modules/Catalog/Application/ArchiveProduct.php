<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;

class ArchiveProduct
{
    public function handle(Tenant $tenant, Product $product): Product
    {
        $scopedProduct = $tenant->products()->findOrFail($product->getKey());
        $scopedProduct->update(['status' => ProductStatus::Archived]);

        return $scopedProduct;
    }
}
