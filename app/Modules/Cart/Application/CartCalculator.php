<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\Enums\CartStatus;
use App\Modules\Cart\Domain\Models\Cart;
use App\Modules\Cart\Domain\Models\CartItem;
use App\Modules\Inventory\Application\TenantInventoryAvailability;
use App\Modules\Listings\Application\ListingEligibility;
use App\Modules\Listings\Application\ListingPriceResolver;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Promotions\Application\EffectivePrice;
use App\Modules\Promotions\Application\EffectivePriceCalculator;
use App\Modules\Tax\Application\ResolveEffectiveTaxRate;
use App\Modules\Tax\Application\TaxCalculator;
use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use App\Modules\Tax\Domain\Models\TaxRate;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartCalculator
{
    public function __construct(
        private readonly ListingPriceResolver $listingPrices,
        private readonly EffectivePriceCalculator $effectivePrices,
        private readonly ListingEligibility $eligibility,
        private readonly TenantInventoryAvailability $availability,
        private readonly CartValidator $validator,
        private readonly ResolveEffectiveTaxRate $taxRates,
        private readonly TaxCalculator $taxCalculator,
    ) {}

    public function calculate(Cart $cart, ?CarbonImmutable $calculatedAt = null): CartSummary
    {
        return $this->calculateMany(collect([$cart]), $calculatedAt)->get($cart->getKey());
    }

    /** @param Collection<int, Cart> $carts
     * @return Collection<string, CartSummary>
     */
    public function calculateMany(Collection $carts, ?CarbonImmutable $calculatedAt = null): Collection
    {
        if ($carts->isEmpty()) {
            return collect();
        }

        $carts = new EloquentCollection($carts->all());

        $expired = $carts->filter(fn (Cart $cart) => $cart->status === CartStatus::Active && $cart->expires_at?->lte(now()));
        if ($expired->isNotEmpty()) {
            Cart::query()->whereKey($expired->pluck('id'))->where('status', CartStatus::Active)
                ->where('expires_at', '<=', now())->update(['status' => CartStatus::Expired->value]);
            $statuses = Cart::query()->whereKey($expired->pluck('id'))->pluck('status', 'id');
            $expired->each(fn (Cart $cart) => $cart->status = CartStatus::from($statuses[$cart->getKey()]));
        }

        $carts->loadMissing(['tenant', 'items.variant', 'items.listing.store', 'items.listing.product.taxCategory']);
        $items = $carts->pluck('items')->flatten(1);
        $variantIds = $items->pluck('product_variant_id')->unique()->values();
        $carts->loadMissing(['items.listing.variants' => fn ($query) => $query->whereIn('product_variant_id', $variantIds)]);
        $listings = $items->pluck('listing')->unique('id')->values();
        $priceLists = $this->listingPrices->priceListsFor($listings);
        $effectiveByItem = collect();
        $calculatedAt ??= CarbonImmutable::now('UTC');

        $items->groupBy(fn (CartItem $item) => $priceLists->get($item->store_listing_id)?->getKey() ?? 'missing')
            ->each(function (Collection $group, string $priceListId) use ($carts, $priceLists, $effectiveByItem, $calculatedAt): void {
                if ($priceListId === 'missing') {
                    return;
                }
                /** @var PriceList $priceList */
                $priceList = $priceLists->first(fn (?PriceList $list) => $list?->getKey() === $priceListId);
                $tenant = $carts->firstWhere('tenant_id', $group->first()->tenant_id)->tenant;
                $prices = $this->effectivePrices->calculateMany($tenant, $group->pluck('variant')->unique('id')->values(), $priceList, $calculatedAt);
                $group->each(fn (CartItem $item) => $effectiveByItem->put($item->getKey(), $prices->get($item->product_variant_id)));
            });

        $availability = collect();
        $items->groupBy('tenant_id')->each(function (Collection $tenantItems, string $tenantId) use ($carts, $availability): void {
            $tenant = $carts->firstWhere('tenant_id', $tenantId)->tenant;
            $this->availability->forVariants($tenant, $tenantItems->pluck('variant')->unique('id')->values())
                ->each(fn (int $amount, string $variantId) => $availability->put($tenantId.':'.$variantId, $amount));
        });

        $resolvedRates = [];

        return $carts->mapWithKeys(fn (Cart $cart): array => [
            $cart->getKey() => $this->summarize(
                $cart, $priceLists, $effectiveByItem, $availability, $calculatedAt, $resolvedRates,
            ),
        ]);
    }

    private function summarize(
        Cart $cart,
        Collection $priceLists,
        Collection $effectiveByItem,
        Collection $availability,
        CarbonImmutable $calculatedAt,
        array &$resolvedRates,
    ): CartSummary {
        $baseTotal = BigDecimal::zero()->toScale(4);
        $discountTotal = BigDecimal::zero()->toScale(4);
        $finalTotal = BigDecimal::zero()->toScale(4);
        $taxableTotal = BigDecimal::zero()->toScale(4);
        $exemptTotal = BigDecimal::zero()->toScale(4);
        $unaffectedTotal = BigDecimal::zero()->toScale(4);
        $taxTotal = BigDecimal::zero()->toScale(4);
        $valid = $cart->status === CartStatus::Active;
        $lines = [];

        foreach ($cart->items as $item) {
            /** @var PriceList|null $priceList */
            $priceList = $priceLists->get($item->store_listing_id);
            /** @var EffectivePrice|null $price */
            $price = $effectiveByItem->get($item->getKey());
            $eligibility = $this->eligibility->evaluateLoaded($item->listing, $item->variant, $price !== null);
            $available = $availability->get($cart->tenant_id.':'.$item->product_variant_id, 0);
            $issues = $this->validator->issues($cart, $item, $eligibility, $priceList?->currency, $available);

            $lineBase = $price === null ? null : BigDecimal::of($price->baseAmount)->multipliedBy($item->quantity)->toScale(4);
            $lineDiscount = $price === null ? null : BigDecimal::of($price->totalDiscount)->multipliedBy($item->quantity)->toScale(4);
            $lineFinal = $price === null ? null : BigDecimal::of($price->finalAmount)->multipliedBy($item->quantity)->toScale(4);
            if ($lineBase !== null) {
                $baseTotal = $baseTotal->plus($lineBase);
                $discountTotal = $discountTotal->plus($lineDiscount);
                $finalTotal = $finalTotal->plus($lineFinal);
            }
            $tax = null;
            $category = $item->listing->product->taxCategory;
            if ($category === null || $category->tenant_id !== $cart->tenant_id) {
                $issues[] = 'TAX_CATEGORY_MISSING';
            } elseif ($lineFinal !== null) {
                $operationDate = $calculatedAt->setTimezone($item->listing->store->timezone);
                $rateKey = $category->getKey().':'.$operationDate->toDateString();
                if (! array_key_exists($rateKey, $resolvedRates)) {
                    try {
                        $resolvedRates[$rateKey] = $this->taxRates->handle($category, $operationDate);
                    } catch (ValidationException $exception) {
                        $message = $exception->errors()['tax_rate'][0] ?? null;
                        $resolvedRates[$rateKey] = str_starts_with((string) $message, 'No active')
                            ? 'TAX_RATE_MISSING'
                            : 'TAX_CONFIGURATION_INVALID';
                    }
                }
                $resolved = $resolvedRates[$rateKey];
                if (! $resolved instanceof TaxRate) {
                    $issues[] = $resolved;
                } else {
                    $calculation = $this->taxCalculator->calculateInclusive((string) $lineFinal, $category, $resolved);
                    $tax = [
                        'tax_category_code' => $calculation->categoryCode->value,
                        'tax_rate' => $calculation->rate,
                        'taxable_amount' => $calculation->categoryCode === TaxCategoryCode::Standard ? $calculation->baseAmount : '0.0000',
                        'exempt_amount' => $calculation->categoryCode === TaxCategoryCode::Exempt ? $calculation->baseAmount : '0.0000',
                        'unaffected_amount' => $calculation->categoryCode === TaxCategoryCode::Unaffected ? $calculation->baseAmount : '0.0000',
                        'tax_amount' => $calculation->taxAmount,
                    ];
                    $taxableTotal = $taxableTotal->plus($tax['taxable_amount']);
                    $exemptTotal = $exemptTotal->plus($tax['exempt_amount']);
                    $unaffectedTotal = $unaffectedTotal->plus($tax['unaffected_amount']);
                    $taxTotal = $taxTotal->plus($tax['tax_amount']);
                }
            }
            $issues = array_values(array_unique($issues));
            $valid = $valid && $issues === [];
            $lines[] = [
                'id' => $item->getKey(),
                'listing_id' => $item->store_listing_id,
                'product_id' => $item->product_id,
                'variant_id' => $item->product_variant_id,
                'price_list_id' => $priceList?->getKey(),
                'product' => $item->listing->product->name,
                'variant' => $item->variant->name ?: $item->variant->sku,
                'sku' => $item->variant->sku,
                'quantity' => $item->quantity,
                'available' => $available,
                'base_unit_price' => $price?->baseAmount,
                'final_unit_price' => $price?->finalAmount,
                'line_base_total' => $lineBase === null ? null : (string) $lineBase,
                'line_discount' => $lineDiscount === null ? null : (string) $lineDiscount,
                'line_total' => $lineFinal === null ? null : (string) $lineFinal,
                'applied_promotions' => $price?->toArray()['applied_promotions'] ?? [],
                ...($tax ?? [
                    'tax_category_code' => $category?->code->value,
                    'tax_rate' => null,
                    'taxable_amount' => null,
                    'exempt_amount' => null,
                    'unaffected_amount' => null,
                    'tax_amount' => null,
                ]),
                'issues' => $issues,
            ];
        }

        return new CartSummary(
            $cart->getKey(),
            $cart->currency,
            $cart->items->sum('quantity'),
            (string) $baseTotal,
            (string) $discountTotal,
            (string) $finalTotal,
            (string) $taxableTotal,
            (string) $exemptTotal,
            (string) $unaffectedTotal,
            (string) $taxTotal,
            $valid,
            $lines,
        );
    }
}
