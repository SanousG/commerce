<?php

namespace App\Modules\Promotions\Application;

use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Pricing\Application\PriceResolver;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Pricing\Domain\Models\VariantPrice;
use App\Modules\Promotions\Domain\Enums\PromotionDiscountType;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class EffectivePriceCalculator
{
    public function __construct(
        private readonly PriceResolver $prices,
        private readonly PromotionMatcher $promotions,
    ) {}

    public function calculate(
        Tenant $tenant,
        ProductVariant $variant,
        ?PriceList $priceList = null,
        ?CarbonInterface $at = null,
    ): ?EffectivePrice {
        $basePrice = $this->prices->priceFor($tenant, $variant, $priceList);
        if ($basePrice === null) {
            return null;
        }

        $list = $basePrice->priceList()->firstOrFail();
        $base = BigDecimal::of($basePrice->amount)->toScale(4);

        return $this->resolve($base, $this->promotions->forVariant($tenant, $variant, $list, $at ?? CarbonImmutable::now()));
    }

    /**
     * Resolves a listing without issuing queries per variant.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<string, EffectivePrice|null>
     */
    public function calculateMany(
        Tenant $tenant,
        Collection $variants,
        ?PriceList $priceList = null,
        ?CarbonInterface $at = null,
    ): Collection {
        $variantIds = $variants->map(fn (ProductVariant $variant) => $variant->getKey())->all();
        $scopedVariants = $tenant->productVariants()->whereKey($variantIds)->get();
        if ($scopedVariants->count() !== count(array_unique($variantIds))) {
            throw (new ModelNotFoundException)->setModel(ProductVariant::class, $variantIds);
        }
        $list = $priceList === null
            ? $tenant->priceLists()->where('is_default', true)->where('status', PriceListStatus::Active)->firstOrFail()
            : $tenant->priceLists()->findOrFail($priceList->getKey());
        $prices = VariantPrice::query()->where('tenant_id', $tenant->getKey())
            ->where('price_list_id', $list->getKey())->whereIn('product_variant_id', $variantIds)
            ->get()->keyBy('product_variant_id');
        $matches = $this->promotions->forVariants($tenant, $scopedVariants, $list, $at ?? CarbonImmutable::now());

        return $scopedVariants->mapWithKeys(function (ProductVariant $variant) use ($prices, $matches): array {
            $price = $prices->get($variant->getKey());

            return [$variant->getKey() => $price === null
                ? null
                : $this->resolve(BigDecimal::of($price->amount)->toScale(4), $matches->get($variant->getKey(), collect()))];
        });
    }

    /** @param Collection<int, Promotion> $promotions */
    private function resolve(BigDecimal $base, Collection $promotions): EffectivePrice
    {
        $winner = null;
        $winningDiscount = BigDecimal::zero()->toScale(4);
        foreach ($promotions as $promotion) {
            $discount = $this->discountFor($base, $promotion);
            if ($winner === null || $promotion->priority > $winner->priority
                || ($promotion->priority === $winner->priority && $discount->isGreaterThan($winningDiscount))) {
                $winner = $promotion;
                $winningDiscount = $discount;
            }
        }

        return new EffectivePrice((string) $base, (string) $base->minus($winningDiscount)->toScale(4), (string) $winningDiscount, $winner);
    }

    private function discountFor(BigDecimal $base, Promotion $promotion): BigDecimal
    {
        $discount = $promotion->discount_type === PromotionDiscountType::Percentage
            ? $base->multipliedBy($promotion->discount_value)->dividedBy(100, 4, RoundingMode::HalfUp)
            : BigDecimal::of($promotion->discount_value)->toScale(4);

        return $discount->isGreaterThan($base) ? $base : $discount;
    }
}
