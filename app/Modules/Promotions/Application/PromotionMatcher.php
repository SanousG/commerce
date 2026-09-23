<?php

namespace App\Modules\Promotions\Application;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Promotions\Domain\Enums\PromotionDiscountType;
use App\Modules\Promotions\Domain\Enums\PromotionScope;
use App\Modules\Promotions\Domain\Enums\PromotionStatus;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PromotionMatcher
{
    /** @return Collection<int, Promotion> */
    public function forVariant(Tenant $tenant, ProductVariant $variant, PriceList $priceList, CarbonInterface $at): Collection
    {
        $scopedVariant = $tenant->productVariants()->findOrFail($variant->getKey());
        $scopedList = $tenant->priceLists()->findOrFail($priceList->getKey());

        return $this->forVariants($tenant, collect([$scopedVariant]), $scopedList, $at)
            ->get($scopedVariant->getKey(), collect());
    }

    /**
     * Returns eligible promotions keyed by variant ID with a bounded query count.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<string, Collection<int, Promotion>>
     */
    public function forVariants(Tenant $tenant, Collection $variants, PriceList $priceList, CarbonInterface $at): Collection
    {
        $variantIds = $variants->map(fn (ProductVariant $variant) => $variant->getKey())->all();
        $scopedVariants = $tenant->productVariants()->whereKey($variantIds)->get();
        if ($scopedVariants->count() !== count(array_unique($variantIds))) {
            throw (new ModelNotFoundException)->setModel(ProductVariant::class, $variantIds);
        }
        $scopedList = $tenant->priceLists()->findOrFail($priceList->getKey());
        $promotions = $tenant->promotions()
            ->where('status', PromotionStatus::Active)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at))
            ->where(function ($query) use ($scopedList) {
                $query->where('discount_type', PromotionDiscountType::Percentage)
                    ->orWhere(fn ($fixed) => $fixed
                        ->where('discount_type', PromotionDiscountType::FixedAmount)
                        ->where('currency', $scopedList->currency));
            })
            ->with(['products:id', 'categories:id', 'variants:id'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        $productCategoryIds = DB::table('category_product')
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('product_id', $scopedVariants->pluck('product_id'))
            ->get(['product_id', 'category_id'])
            ->groupBy('product_id')
            ->map(fn (Collection $rows) => $rows->pluck('category_id')->all());

        return $scopedVariants->mapWithKeys(function (ProductVariant $variant) use ($promotions, $productCategoryIds): array {
            $matches = $promotions->filter(function (Promotion $promotion) use ($variant, $productCategoryIds): bool {
                if ($promotion->scope === PromotionScope::All) {
                    return true;
                }

                return in_array($variant->getKey(), $promotion->variants->modelKeys(), true)
                    || in_array($variant->product_id, $promotion->products->modelKeys(), true)
                    || array_intersect($productCategoryIds->get($variant->product_id, []), $promotion->categories->modelKeys()) !== [];
            })->values();

            return [$variant->getKey() => $matches];
        });
    }
}
