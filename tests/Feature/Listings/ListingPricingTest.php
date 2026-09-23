<?php

namespace Tests\Feature\Listings;

use App\Models\User;
use App\Modules\Listings\Application\ListingPriceResolver;
use App\Modules\Listings\Application\SetStoreDefaultPriceList;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Pricing\Domain\Models\VariantPrice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

class ListingPricingTest extends ListingsFeatureTestCase
{
    public function test_effective_price_list_falls_back_from_listing_to_store_to_tenant_default(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $default = $tenant->defaultPriceList()->sole();
        $storeList = $this->createPriceList($tenant, 'Store prices');
        $listingList = $this->createPriceList($tenant, 'Listing prices');
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $variant = $product->variants()->sole();
        $listing = $this->createListing($store, $product);
        $this->priceVariant($tenant, $default, $variant, '10');
        $this->priceVariant($tenant, $storeList, $variant, '20');
        $this->priceVariant($tenant, $listingList, $variant, '30');
        $resolver = app(ListingPriceResolver::class);

        $this->assertSame('10.0000', $resolver->priceFor($listing, $variant)?->amount);
        app(SetStoreDefaultPriceList::class)->handle($tenant, $store, $storeList->getKey());
        $this->assertSame('20.0000', $resolver->priceFor($listing->fresh(), $variant)?->amount);
        $listing->update(['price_list_id' => $listingList->getKey()]);
        $this->assertSame('30.0000', $resolver->priceFor($listing->fresh(), $variant)?->amount);
    }

    public function test_inactive_or_foreign_price_lists_are_not_usable(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $listing = $this->createListing($store, $this->createProduct($tenant));
        $inactive = $this->createPriceList($tenant, 'Inactive');
        $inactive->update(['status' => PriceListStatus::Inactive]);
        $listing->update(['price_list_id' => $inactive->getKey()]);

        $this->assertNull(app(ListingPriceResolver::class)->priceListFor($listing->fresh()));

        foreach ([$inactive, $this->createTenantFor(User::factory()->create())->defaultPriceList()->sole()] as $invalid) {
            try {
                app(SetStoreDefaultPriceList::class)->handle($tenant, $store, $invalid->getKey());
                $this->fail('An inactive or foreign price list must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('default_price_list_id', $exception->errors());
            }
        }
    }

    public function test_price_resolution_rejects_cross_tenant_and_other_product_variants(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $listing = $this->createListing($this->createStore($tenant), $this->createProduct($tenant));
        $foreign = $this->createProduct($this->createTenantFor(User::factory()->create()))->variants()->sole();

        $this->expectException(ModelNotFoundException::class);
        app(ListingPriceResolver::class)->priceFor($listing, $foreign);
    }

    public function test_listing_edit_previews_promotions_without_mutating_prices_or_promotions(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $variant = $product->variants()->sole();
        $listing = $this->createListing($store, $product);
        $this->priceVariant($tenant, $tenant->defaultPriceList()->sole(), $variant, '100');
        $promotion = $this->createPromotion($tenant);
        $priceBefore = VariantPrice::query()->sole()->getRawOriginal();
        $promotionBefore = $promotion->refresh()->getRawOriginal();
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.listings.edit', $listing))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/listings/Edit')
                ->where('variants.data.0.price.base_amount', '100.0000')
                ->where('variants.data.0.price.final_amount', '75.0000')
                ->where('variants.data.0.price.total_discount', '25.0000')
                ->where('variants.data.0.price.applied_promotions.0.id', $promotion->getKey()));

        $this->assertSame($priceBefore, VariantPrice::query()->sole()->getRawOriginal());
        $this->assertSame($promotionBefore, $promotion->fresh()->getRawOriginal());
        $this->assertSame(1, VariantPrice::query()->count());
    }
}
