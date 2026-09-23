<?php

namespace Tests\Feature\Promotions;

use App\Models\User;
use App\Modules\Catalog\Application\SaveCategory;
use App\Modules\Pricing\Application\CreatePriceList;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Promotions\Application\EffectivePriceCalculator;
use Carbon\CarbonImmutable;

class EffectivePriceTest extends PromotionsFeatureTestCase
{
    public function test_percentage_and_fixed_discounts_use_exact_four_decimal_calculation(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $this->priceVariant($tenant, $variant, '99.99');
        $promotion = $this->createPromotion($tenant, ['discount_value' => '15']);

        $result = app(EffectivePriceCalculator::class)->calculate($tenant, $variant);
        $this->assertSame('99.9900', $result?->baseAmount);
        $this->assertSame('14.9985', $result?->totalDiscount);
        $this->assertSame('84.9915', $result?->finalAmount);
        $this->assertSame($promotion->getKey(), $result?->promotion?->getKey());

        $promotion->update(['discount_type' => 'FIXED_AMOUNT', 'discount_value' => '20', 'currency' => 'PEN']);
        $result = app(EffectivePriceCalculator::class)->calculate($tenant, $variant);
        $this->assertSame('79.9900', $result?->finalAmount);
    }

    public function test_discount_is_floored_at_zero_and_one_hundred_percent_is_allowed(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $this->priceVariant($tenant, $variant, '5');
        $promotion = $this->createPromotion($tenant, [
            'discount_type' => 'FIXED_AMOUNT', 'discount_value' => '10', 'currency' => 'PEN',
        ]);
        $this->assertSame('0.0000', app(EffectivePriceCalculator::class)->calculate($tenant, $variant)?->finalAmount);

        $promotion->update(['discount_type' => 'PERCENTAGE', 'discount_value' => '100', 'currency' => null]);
        $this->assertSame('0.0000', app(EffectivePriceCalculator::class)->calculate($tenant, $variant)?->finalAmount);
    }

    public function test_currency_rules_apply_fixed_only_to_matching_list_while_percentage_is_currency_independent(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $pen = $tenant->defaultPriceList()->sole();
        $usd = app(CreatePriceList::class)->handle($tenant, ['name' => 'USD', 'code' => 'USD', 'currency' => 'USD']);
        app(SetVariantPrice::class)->handle($tenant, $pen, $variant, '100');
        app(SetVariantPrice::class)->handle($tenant, $usd, $variant, '100');
        $promotion = $this->createPromotion($tenant, [
            'discount_type' => 'FIXED_AMOUNT', 'discount_value' => '20', 'currency' => 'PEN',
        ]);

        $calculator = app(EffectivePriceCalculator::class);
        $this->assertSame('80.0000', $calculator->calculate($tenant, $variant, $pen)?->finalAmount);
        $this->assertSame('100.0000', $calculator->calculate($tenant, $variant, $usd)?->finalAmount);
        $promotion->update(['discount_type' => 'PERCENTAGE', 'discount_value' => '20', 'currency' => null]);
        $this->assertSame('80.0000', $calculator->calculate($tenant, $variant, $usd)?->finalAmount);
    }

    public function test_temporal_eligibility_uses_inclusive_start_and_exclusive_end(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $this->priceVariant($tenant, $variant);
        $promotion = $this->createPromotion($tenant, [
            'starts_at' => '2026-09-12 10:00:00+00', 'ends_at' => '2026-09-12 11:00:00+00',
        ]);
        $calculator = app(EffectivePriceCalculator::class);

        $this->assertSame('100.0000', $calculator->calculate($tenant, $variant, null, CarbonImmutable::parse('2026-09-12 09:59:59Z'))?->finalAmount);
        $this->assertSame('80.0000', $calculator->calculate($tenant, $variant, null, CarbonImmutable::parse('2026-09-12 10:00:00Z'))?->finalAmount);
        $this->assertSame('80.0000', $calculator->calculate($tenant, $variant, null, CarbonImmutable::parse('2026-09-12 10:59:59Z'))?->finalAmount);
        $this->assertSame('100.0000', $calculator->calculate($tenant, $variant, null, CarbonImmutable::parse('2026-09-12 11:00:00Z'))?->finalAmount);

        foreach (['DRAFT', 'INACTIVE', 'ARCHIVED'] as $status) {
            $promotion->update(['status' => $status]);
            $this->assertSame('100.0000', $calculator->calculate($tenant, $variant, null, CarbonImmutable::parse('2026-09-12 10:30:00Z'))?->finalAmount);
        }
    }

    public function test_all_product_category_and_variant_targets_match_as_an_or_union(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $productTarget = $this->createVariant($tenant, 'PRODUCT-TARGET');
        $categoryTarget = $this->createVariant($tenant, 'CATEGORY-TARGET');
        $variantTarget = $this->createVariant($tenant, 'VARIANT-TARGET');
        $untargeted = $this->createVariant($tenant, 'NO-TARGET');
        foreach ([$productTarget, $categoryTarget, $variantTarget, $untargeted] as $variant) {
            $this->priceVariant($tenant, $variant);
        }
        $category = app(SaveCategory::class)->create($tenant, ['name' => 'Exact category', 'status' => 'ACTIVE', 'position' => 0]);
        $categoryTarget->product->categories()->attach($category, ['tenant_id' => $tenant->getKey()]);
        $this->createPromotion($tenant, [
            'scope' => 'SELECTED',
            'product_ids' => [$productTarget->product_id],
            'category_ids' => [$category->getKey()],
            'variant_ids' => [$variantTarget->getKey()],
        ]);
        $calculator = app(EffectivePriceCalculator::class);

        $this->assertSame('80.0000', $calculator->calculate($tenant, $productTarget)?->finalAmount);
        $this->assertSame('80.0000', $calculator->calculate($tenant, $categoryTarget)?->finalAmount);
        $this->assertSame('80.0000', $calculator->calculate($tenant, $variantTarget)?->finalAmount);
        $this->assertSame('100.0000', $calculator->calculate($tenant, $untargeted)?->finalAmount);
    }

    public function test_higher_priority_wins_then_larger_discount_then_uuid_order(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $this->priceVariant($tenant, $variant);
        $large = $this->createPromotion($tenant, ['name' => 'Large', 'internal_code' => 'LARGE', 'discount_value' => '50', 'priority' => 0]);
        $priority = $this->createPromotion($tenant, ['name' => 'Priority', 'internal_code' => 'PRIORITY', 'discount_value' => '10', 'priority' => 1]);
        $calculator = app(EffectivePriceCalculator::class);
        $this->assertSame($priority->getKey(), $calculator->calculate($tenant, $variant)?->promotion?->getKey());
        $priority->update(['priority' => 0]);
        $this->assertSame($large->getKey(), $calculator->calculate($tenant, $variant)?->promotion?->getKey());

        $priority->update(['discount_value' => '50']);
        $expected = collect([$large->getKey(), $priority->getKey()])->sort()->first();
        $this->assertSame($expected, $calculator->calculate($tenant, $variant)?->promotion?->getKey());
    }

    public function test_unpriced_variant_returns_null_and_calculation_does_not_mutate_base_price(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $calculator = app(EffectivePriceCalculator::class);
        $this->assertNull($calculator->calculate($tenant, $variant));
        $this->priceVariant($tenant, $variant);
        $this->createPromotion($tenant);
        $this->assertSame('80.0000', $calculator->calculate($tenant, $variant)?->finalAmount);
        $this->assertSame('100.0000', $variant->prices()->sole()->amount);
    }

    public function test_batch_calculation_resolves_multiple_variants_and_unpriced_values(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $priced = $this->createVariant($tenant, 'BATCH-PRICED');
        $unpriced = $this->createVariant($tenant, 'BATCH-UNPRICED');
        $this->priceVariant($tenant, $priced);
        $this->createPromotion($tenant);

        $results = app(EffectivePriceCalculator::class)->calculateMany($tenant, collect([$priced, $unpriced]));
        $this->assertSame('80.0000', $results->get($priced->getKey())?->finalAmount);
        $this->assertNull($results->get($unpriced->getKey()));
    }

    public function test_effective_price_uses_only_the_explicit_tenant_promotions(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $variantA = $this->createVariant($tenantA, 'TENANT-A');
        $variantB = $this->createVariant($tenantB, 'TENANT-B');
        $this->priceVariant($tenantA, $variantA);
        $this->priceVariant($tenantB, $variantB);
        $this->createPromotion($tenantA, ['discount_value' => '10']);
        $this->createPromotion($tenantB, ['discount_value' => '70']);

        $calculator = app(EffectivePriceCalculator::class);
        $this->assertSame('90.0000', $calculator->calculate($tenantA, $variantA)?->finalAmount);
        $this->assertSame('30.0000', $calculator->calculate($tenantB, $variantB)?->finalAmount);
    }
}
