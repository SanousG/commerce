<?php

namespace Tests\Feature\Listings;

use App\Models\User;
use App\Modules\Stores\Application\StoreContext;
use Inertia\Testing\AssertableInertia as Assert;

class ListingIndexTest extends ListingsFeatureTestCase
{
    public function test_index_searches_only_the_current_store_by_product_sku_and_slug(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $listingA = $this->createListing($storeA, $this->createProduct($tenant, [
            'name' => 'Visible Jacket', 'sku' => 'VISIBLE-SKU',
        ]), ['storefront_slug' => 'visible-handle']);
        $this->createListing($storeB, $this->createProduct($tenant, [
            'name' => 'Hidden Jacket', 'sku' => 'HIDDEN-SKU',
        ]), ['storefront_slug' => 'hidden-handle']);
        $this->useContext($user, $tenant, $storeA);

        foreach (['Visible Jacket', 'VISIBLE-SKU', 'visible-handle'] as $search) {
            $this->get(route('admin.listings.index', ['search' => $search]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('admin/listings/Index')
                    ->has('listings.data', 1)
                    ->where('listings.data.0.id', $listingA->getKey())
                    ->where('filters.search', $search));
        }

        $this->get(route('admin.listings.index', ['search' => 'Hidden Jacket']))
            ->assertInertia(fn (Assert $page) => $page->has('listings.data', 0));
    }

    public function test_switching_store_changes_listing_props_immediately(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $listingA = $this->createListing($storeA, $this->createProduct($tenant, ['name' => 'Store A Product']));
        $listingB = $this->createListing($storeB, $this->createProduct($tenant, ['name' => 'Store B Product']));
        $this->useContext($user, $tenant, $storeA);

        $this->get(route('admin.listings.index'))->assertInertia(fn (Assert $page) => $page
            ->where('listings.data.0.id', $listingA->getKey())
            ->where('currentStore.id', $storeA->getKey()));

        $this->withSession([StoreContext::SESSION_KEY => $storeB->getKey()])
            ->get(route('admin.listings.index'))->assertInertia(fn (Assert $page) => $page
            ->has('listings.data', 1)
            ->where('listings.data.0.id', $listingB->getKey())
            ->where('currentStore.id', $storeB->getKey()));
    }

    public function test_index_paginates_twenty_listings(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        foreach (range(1, 21) as $number) {
            $this->createListing($store, $this->createProduct($tenant, [
                'name' => "Product {$number}", 'sku' => "PAGE-{$number}",
            ]));
        }
        $this->useContext($user, $tenant, $store);

        $this->get(route('admin.listings.index'))->assertInertia(fn (Assert $page) => $page
            ->has('listings.data', 20)
            ->where('listings.per_page', 20)
            ->where('listings.total', 21));
    }

    public function test_no_listing_delete_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $listing = $this->createListing($store, $this->createProduct($tenant));
        $this->useContext($user, $tenant, $store);

        $this->delete("/admin/listings/{$listing->getKey()}")->assertMethodNotAllowed();
        $this->assertDatabaseHas('store_listings', ['id' => $listing->getKey()]);
    }
}
