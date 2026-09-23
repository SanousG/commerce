<?php

namespace Tests\Feature\Listings;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Listings\Application\FindPublishedListing;
use App\Modules\Listings\Application\ListingEligibility;
use App\Modules\Listings\Application\PublishListing;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use Illuminate\Validation\ValidationException;

class ListingLifecycleAndAccessTest extends ListingsFeatureTestCase
{
    public function test_publish_reports_missing_enabled_prices_and_lifecycle_preserves_first_publish_time(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $default = $product->variants()->sole();
        $missing = $this->addVariant($tenant, $product, sku: 'MISSING-SKU');
        $listing = $this->createListing($store, $product);
        $list = $tenant->defaultPriceList()->sole();
        $this->priceVariant($tenant, $list, $default, '50');
        $this->useContext($user, $tenant, $store);

        $this->post(route('admin.listings.publish', $listing))
            ->assertSessionHasErrors(['variants' => 'Missing prices for enabled SKUs: MISSING-SKU.']);
        $this->assertSame(StoreListingStatus::Draft, $listing->fresh()->status);

        $this->priceVariant($tenant, $list, $missing, '60');
        $this->post(route('admin.listings.publish', $listing))->assertRedirect();
        $firstPublishedAt = $listing->fresh()->published_at;
        $this->assertSame(StoreListingStatus::Published, $listing->fresh()->status);
        $this->assertSame($listing->getKey(), app(FindPublishedListing::class)->bySlug($store, $listing->storefront_slug)->getKey());

        $this->post(route('admin.listings.unpublish', $listing))->assertRedirect();
        $this->assertSame(StoreListingStatus::Unpublished, $listing->fresh()->status);
        $this->post(route('admin.listings.publish', $listing))->assertRedirect();
        $this->assertTrue($firstPublishedAt->equalTo($listing->fresh()->published_at));

        $this->post(route('admin.listings.archive', $listing))->assertRedirect(route('admin.listings.index'));
        $this->assertSame(StoreListingStatus::Archived, $listing->fresh()->status);
        $this->post(route('admin.listings.publish', $listing))->assertSessionHasErrors('listing');
        $this->post(route('admin.listings.unpublish', $listing))->assertSessionHasErrors('listing');
    }

    public function test_eligibility_returns_stable_reasons_and_accepts_a_sellable_variant(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $default = $product->variants()->sole();
        $problem = $this->addVariant($tenant, $product, ProductVariantStatus::Inactive, 'PROBLEM-SKU');
        $listing = $this->createListing($store, $product);
        $listing->variants()->where('product_variant_id', $problem->getKey())->update(['is_enabled' => false]);
        $product->update(['status' => ProductStatus::Archived]);
        $store->update(['status' => StoreStatus::Inactive]);

        $result = app(ListingEligibility::class)->evaluate($listing, $problem);
        $this->assertFalse($result->eligible);
        $this->assertSame([
            'LISTING_NOT_PUBLISHED',
            'PRODUCT_INACTIVE',
            'STORE_INACTIVE',
            'VARIANT_INACTIVE',
            'VARIANT_DISABLED',
            'PRICE_MISSING',
        ], $result->reasons);

        $product->update(['status' => ProductStatus::Active]);
        $store->update(['status' => StoreStatus::Active]);
        $listing->update(['status' => StoreListingStatus::Published, 'published_at' => now()]);
        $this->priceVariant($tenant, $tenant->defaultPriceList()->sole(), $default, '25');
        $eligible = app(ListingEligibility::class)->evaluate($listing->fresh(), $default);
        $this->assertTrue($eligible->eligible);
        $this->assertSame([], $eligible->reasons);

        $otherProductVariant = $this->createProduct($tenant)->variants()->sole();
        $this->assertSame(
            ['VARIANT_NOT_IN_PRODUCT'],
            app(ListingEligibility::class)->evaluate($listing->fresh(), $otherProductVariant)->reasons,
        );
    }

    public function test_same_tenant_cross_store_and_cross_tenant_listing_urls_return_not_found(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $currentStore = $this->createStore($tenant);
        $otherStore = $this->createStore($tenant);
        $otherStoreListing = $this->createListing($otherStore, $this->createProduct($tenant));
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $foreignListing = $this->createListing($this->createStore($foreignTenant), $this->createProduct($foreignTenant));
        $this->useContext($user, $tenant, $currentStore);

        $this->get(route('admin.listings.edit', $otherStoreListing))->assertNotFound();
        $this->post(route('admin.listings.publish', $otherStoreListing))->assertNotFound();
        $this->put(route('admin.listings.update', $foreignListing), [
            'storefront_slug' => 'attack', 'price_list_id' => null, 'enabled_variant_ids' => [],
        ])->assertNotFound();
        $this->post(route('admin.listings.archive', $foreignListing))->assertNotFound();
    }

    public function test_member_can_view_but_cannot_mutate_listings_or_store_pricing_settings(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, SystemRole::Member);
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $listing = $this->createListing($store, $product);
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.listings.index'))->assertOk();
        $this->get(route('admin.listings.edit', $listing))->assertOk();
        $this->post(route('admin.listings.store'), ['product_id' => $product->getKey()])->assertForbidden();
        $this->put(route('admin.listings.update', $listing), [
            'storefront_slug' => 'forbidden', 'price_list_id' => null, 'enabled_variant_ids' => [],
        ])->assertForbidden();
        $this->post(route('admin.listings.publish', $listing))->assertForbidden();
        $this->post(route('admin.listings.unpublish', $listing))->assertForbidden();
        $this->post(route('admin.listings.archive', $listing))->assertForbidden();
        $this->put(route('admin.listings.settings.update'), ['default_price_list_id' => null])->assertForbidden();
    }

    public function test_publish_rejects_non_active_product_inactive_store_and_no_active_enabled_variant(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $variant = $product->variants()->sole();
        $listing = $this->createListing($store, $product);
        $this->priceVariant($tenant, $tenant->defaultPriceList()->sole(), $variant, '10');
        $publisher = app(PublishListing::class);

        foreach ([ProductStatus::Draft, ProductStatus::Archived] as $status) {
            $product->update(['status' => $status]);
            try {
                $publisher->handle($tenant, $store, $listing);
                $this->fail("A {$status->value} product must not be published.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('listing', $exception->errors());
            }
        }

        $product->update(['status' => ProductStatus::Active]);
        $store->update(['status' => StoreStatus::Inactive]);
        try {
            $publisher->handle($tenant, $store->fresh(), $listing);
            $this->fail('An inactive store must not publish a listing.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('listing', $exception->errors());
        }

        $store->update(['status' => StoreStatus::Active]);
        $listing->variants()->update(['is_enabled' => false]);
        try {
            $publisher->handle($tenant, $store->fresh(), $listing);
            $this->fail('A listing without an active enabled variant must not be published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variants', $exception->errors());
        }
    }
}
