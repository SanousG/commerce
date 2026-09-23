<?php

namespace App\Modules\Pricing\Application;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Pricing\Domain\Models\VariantPrice;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetVariantPrice
{
    public function handle(Tenant $tenant, PriceList $priceList, ProductVariant $variant, string $amount): VariantPrice
    {
        return DB::transaction(function () use ($tenant, $priceList, $variant, $amount): VariantPrice {
            $scopedList = $tenant->priceLists()->lockForUpdate()->findOrFail($priceList->getKey());
            $scopedVariant = ProductVariant::query()
                ->where('tenant_id', $tenant->getKey())
                ->whereKey($variant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($scopedList->status === PriceListStatus::Archived) {
                throw ValidationException::withMessages(['amount' => 'Archived price lists cannot be changed.']);
            }

            return VariantPrice::query()->updateOrCreate(
                [
                    'price_list_id' => $scopedList->getKey(),
                    'product_variant_id' => $scopedVariant->getKey(),
                ],
                ['tenant_id' => $tenant->getKey(), 'amount' => trim($amount)],
            );
        });
    }

    public function remove(Tenant $tenant, PriceList $priceList, ProductVariant $variant): void
    {
        DB::transaction(function () use ($tenant, $priceList, $variant): void {
            $scopedList = $tenant->priceLists()->lockForUpdate()->findOrFail($priceList->getKey());
            $scopedVariant = ProductVariant::query()->where('tenant_id', $tenant->getKey())
                ->whereKey($variant->getKey())->firstOrFail();

            if ($scopedList->status === PriceListStatus::Archived) {
                throw ValidationException::withMessages(['amount' => 'Archived price lists cannot be changed.']);
            }

            VariantPrice::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('price_list_id', $scopedList->getKey())
                ->where('product_variant_id', $scopedVariant->getKey())
                ->delete();
        });
    }
}
