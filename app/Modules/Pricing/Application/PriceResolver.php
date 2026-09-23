<?php

namespace App\Modules\Pricing\Application;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Pricing\Domain\Models\VariantPrice;
use App\Modules\Tenancy\Domain\Models\Tenant;

class PriceResolver
{
    public function priceFor(Tenant $tenant, ProductVariant $variant, ?PriceList $priceList = null): ?VariantPrice
    {
        $scopedVariant = ProductVariant::query()->where('tenant_id', $tenant->getKey())
            ->whereKey($variant->getKey())->firstOrFail();
        $scopedList = $priceList === null
            ? $tenant->priceLists()->where('is_default', true)->where('status', PriceListStatus::Active)->firstOrFail()
            : $tenant->priceLists()->findOrFail($priceList->getKey());

        return VariantPrice::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('price_list_id', $scopedList->getKey())
            ->where('product_variant_id', $scopedVariant->getKey())
            ->first();
    }
}
