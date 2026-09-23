<?php

namespace Tests\Feature\Cart;

use App\Models\User;
use App\Modules\Cart\Application\AddCartItem;
use App\Modules\Cart\Application\CartCalculator;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Pricing\Application\CreatePriceList;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Promotions\Domain\Enums\PromotionStatus;
use Illuminate\Validation\ValidationException;

class CartCalculationTest extends CartsFeatureTestCase
{
    public function test_calculator_uses_exact_promotional_money_and_reflects_changes(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$cart, $listing, $variant] = $this->sellableCart($this->createStore($tenant), '100.0000');
        app(AddCartItem::class)->handle($cart, $listing, $variant, 2);
        $calculator = app(CartCalculator::class);

        $before = $calculator->calculate($cart->fresh());
        $this->assertSame('200.0000', $before->subtotal);
        $promotion = $this->createPromotion($tenant);
        $discounted = $calculator->calculate($cart->fresh());
        $this->assertSame('200.0000', $discounted->subtotalBeforeDiscounts);
        $this->assertSame('50.0000', $discounted->discountTotal);
        $this->assertSame('150.0000', $discounted->subtotal);

        $promotion->update(['status' => PromotionStatus::Inactive]);
        app(SetVariantPrice::class)->handle($tenant, $tenant->defaultPriceList()->sole(), $variant, '80.0000');
        $changed = $calculator->calculate($cart->fresh());
        $this->assertSame('160.0000', $changed->subtotal);
    }

    public function test_validator_reports_changes_without_deleting_the_line(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        [$cart, $listing, $variant, $level] = $this->sellableCart($this->createStore($tenant), stock: 5, additionalVariant: true);
        app(AddCartItem::class)->handle($cart, $listing, $variant, 5);
        $calculator = app(CartCalculator::class);

        $listing->update(['status' => StoreListingStatus::Unpublished]);
        $variant->update(['status' => ProductVariantStatus::Inactive]);
        $level->update(['on_hand' => 2]);
        $summary = $calculator->calculate($cart->fresh());

        $this->assertFalse($summary->valid);
        $this->assertContains('LISTING_UNAVAILABLE', $summary->items[0]['issues']);
        $this->assertContains('VARIANT_INACTIVE', $summary->items[0]['issues']);
        $this->assertContains('INSUFFICIENT_STOCK', $summary->items[0]['issues']);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_cart_rejects_a_second_currency_without_fx_conversion(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        [$cart, $firstListing, $firstVariant] = $this->sellableCart($store);
        app(AddCartItem::class)->handle($cart, $firstListing, $firstVariant, 1);
        $product = $this->createProduct($tenant);
        $variant = $product->variants()->sole();
        $usd = app(CreatePriceList::class)->handle($tenant, ['name' => 'USD', 'code' => 'USD-CART', 'currency' => 'USD']);
        app(SetVariantPrice::class)->handle($tenant, $usd, $variant, '10.0000');
        $listing = $this->createListing($store, $product, ['price_list_id' => $usd->getKey()]);
        $listing->update(['status' => StoreListingStatus::Published, 'published_at' => now()]);
        $this->stock($tenant, $variant, 10);

        $this->expectException(ValidationException::class);
        app(AddCartItem::class)->handle($cart->fresh(), $listing->fresh(), $variant, 1);
    }
}
