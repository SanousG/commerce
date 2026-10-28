<?php

namespace Tests\Feature\Storefront;

use App\Modules\Catalog\Domain\Enums\CategoryStatus;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\ProductMedia;
use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Application\SaveVariant;
use App\Modules\Listings\Application\CreateListing;
use App\Modules\Listings\Application\PublishListing;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Listings\Application\UnpublishListing;
use App\Modules\Media\Domain\Enums\MediaDerivativeVariant;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Enums\MediaType;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Media\Domain\Models\MediaDerivative;
use App\Modules\Promotions\Application\CreatePromotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

class EssentialCatalogTest extends StorefrontFeatureTestCase
{
    public function test_home_catalog_and_product_expose_only_authoritative_public_data(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant, attributes: ['display_name' => 'Essential Store']);
        $this->publish($store, 'essential-store');
        $record = $this->createPublishedProduct($store, ['name' => 'Oxford Shirt', 'slug' => 'oxford-shirt', 'stock' => 8]);

        $category = Category::query()->create([
            'tenant_id' => $tenant->getKey(), 'name' => 'Clothing', 'slug' => 'clothing',
            'status' => CategoryStatus::Active, 'position' => 1,
        ]);
        $record['product']->categories()->attach($category, ['tenant_id' => $tenant->getKey()]);

        $this->get('/tienda/essential-store')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Home')
            ->where('store.theme', 'essential')
            ->where('featuredProducts.0.name', 'Oxford Shirt')
            ->where('featuredProducts.0.price.finalAmount', '129.0000')
            ->where('featuredProducts.0.price.currency', 'PEN')
            ->where('featuredProducts.0.availability.state', 'available')
            ->where('categories.0.name', 'Clothing')
            ->missing('featuredProducts.0.variants'));

        $this->get('/tienda/essential-store/productos')->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Catalog')->where('products.total', 1)->where('products.data.0.slug', 'oxford-shirt'));

        $this->get('/tienda/essential-store/productos/oxford-shirt')->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Product')
            ->where('product.name', 'Oxford Shirt')
            ->where('product.variants.0.price.finalAmount', '129.0000')
            ->where('product.variants.0.availability.state', 'available')
            ->missing('product.variants.0.sku')->missing('product.variants.0.availableUnits'));
    }

    public function test_search_categories_and_pagination_are_store_scoped(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);
        $this->publish($store, 'catalog-store');
        $category = Category::query()->create([
            'tenant_id' => $tenant->getKey(), 'name' => 'Home', 'slug' => 'home',
            'status' => CategoryStatus::Active, 'position' => 0,
        ]);
        foreach (range(1, 13) as $index) {
            $record = $this->createPublishedProduct($store, ['name' => "Lamp {$index}", 'slug' => "lamp-{$index}"]);
            $record['product']->categories()->attach($category, ['tenant_id' => $tenant->getKey()]);
        }

        $this->get('/tienda/catalog-store/productos?q=Lamp+13')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)->where('products.data.0.name', 'Lamp 13')->where('filters.search', 'Lamp 13'));
        $this->get('/tienda/catalog-store/categorias/home')->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 13)->where('products.last_page', 2)->has('products.data', 12));
        $this->get('/tienda/catalog-store/categorias/unknown')->assertNotFound();
    }

    public function test_promotions_and_unavailable_inventory_are_resolved_by_laravel(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);
        $this->publish($store, 'pricing-store');
        $this->createPublishedProduct($store, ['name' => 'Promoted item', 'slug' => 'promoted-item', 'price' => '100.0000', 'stock' => 0]);
        app(CreatePromotion::class)->handle($tenant, [
            'name' => 'Real promotion', 'internal_code' => 'PUBLIC-REAL', 'description' => null,
            'status' => 'ACTIVE', 'discount_type' => 'PERCENTAGE', 'discount_value' => '20',
            'currency' => null, 'starts_at' => null, 'ends_at' => null, 'priority' => 10,
            'scope' => 'ALL', 'product_ids' => [], 'category_ids' => [], 'variant_ids' => [],
        ]);

        $this->get('/tienda/pricing-store/productos/promoted-item')->assertInertia(fn (Assert $page) => $page
            ->where('product.price.baseAmount', '100.0000')
            ->where('product.price.finalAmount', '80.0000')
            ->where('product.price.discountAmount', '20.0000')
            ->where('product.price.promotionName', 'Real promotion')
            ->where('product.availability.state', 'unavailable'));
    }

    public function test_product_detail_projects_each_eligible_variant_with_server_resolved_results(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);
        $this->publish($store, 'variant-store');
        $product = app(CreateProduct::class)->handle($tenant, ['name' => 'Variant product', 'sku' => 'SF-DEFAULT']);
        $product->update(['status' => ProductStatus::Active]);
        $second = app(SaveVariant::class)->create($tenant, $product, [
            'sku' => 'SF-SECOND', 'name' => 'Second option', 'status' => 'ACTIVE',
            'is_default' => false, 'option_value_ids' => [],
        ]);
        $list = $tenant->priceLists()->where('is_default', true)->firstOrFail();
        app(SetVariantPrice::class)->handle($tenant, $list, $product->defaultVariant()->firstOrFail(), '10.0000');
        app(SetVariantPrice::class)->handle($tenant, $list, $second, '12.5000');
        $listing = app(CreateListing::class)->handle($tenant, $store, [
            'product_id' => $product->getKey(), 'storefront_slug' => 'variant-product',
        ]);
        app(PublishListing::class)->handle($tenant, $store, $listing);

        $this->get('/tienda/variant-store/productos/variant-product')->assertInertia(fn (Assert $page) => $page
            ->has('product.variants', 2)
            ->where('product.variants.0.price.currency', 'PEN')
            ->where('product.variants.1.price.finalAmount', '12.5000')
            ->missing('product.variants.1.sku'));
    }

    public function test_unpublished_inactive_and_cross_store_listings_never_leak(): void
    {
        $tenantA = $this->createTenant();
        $storeA = $this->createStore($tenantA);
        $storeA2 = $this->createStore($tenantA);
        $tenantB = $this->createTenant();
        $storeB = $this->createStore($tenantB);
        $this->publish($storeA, 'store-a');
        $this->publish($storeA2, 'store-a2');
        $this->publish($storeB, 'store-b');
        $record = $this->createPublishedProduct($storeA, ['name' => 'Private to A', 'slug' => 'private-a']);

        $this->get('/tienda/store-a/productos/private-a')->assertOk();
        $this->get('/tienda/store-a2/productos/private-a')->assertNotFound();
        $this->get('/tienda/store-b/productos/private-a')->assertNotFound();

        app(UnpublishListing::class)->handle($tenantA, $storeA, $record['listing']);
        $this->get('/tienda/store-a/productos/private-a')->assertNotFound();
        $archived = $this->createPublishedProduct($storeA, ['name' => 'Archived product', 'slug' => 'archived-product']);
        $archived['product']->update(['status' => ProductStatus::Archived]);
        $this->get('/tienda/store-a/productos/archived-product')->assertNotFound();
        $this->get('/tienda/store-a/productos')->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
    }

    public function test_branding_is_isolated_and_internal_props_are_absent(): void
    {
        $tenant = $this->createTenant();
        $first = $this->createStore($tenant, attributes: ['display_name' => 'Blue Store', 'primary_color' => '#0F172A']);
        $second = $this->createStore($tenant, attributes: ['display_name' => 'Green Store', 'primary_color' => '#166534']);
        $this->publish($first, 'blue-store');
        $this->publish($second, 'green-store');

        $this->get('/tienda/blue-store')->assertInertia(fn (Assert $page) => $page
            ->where('store.name', 'Blue Store')->where('store.branding.primaryColor', '#0F172A')
            ->where('store.theme', 'essential')->missing('store.id')->missing('auth'));
        $this->get('/tienda/green-store')->assertInertia(fn (Assert $page) => $page
            ->where('store.name', 'Green Store')->where('store.branding.primaryColor', '#166534'));
    }

    public function test_public_media_serves_only_referenced_derivatives_for_the_current_store(): void
    {
        Storage::fake('local');
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);
        $other = $this->createStore($tenant);
        $this->publish($store, 'media-store');
        $this->publish($other, 'other-store');
        $record = $this->createPublishedProduct($store, ['slug' => 'pictured-product']);
        $checksum = hash('sha256', 'public-derivative');
        $asset = MediaAsset::query()->create([
            'tenant_id' => $tenant->getKey(), 'type' => MediaType::Image, 'disk' => 'local',
            'storage_key' => 'private/master.webp', 'original_filename' => 'safe.webp', 'mime_type' => 'image/webp',
            'extension' => 'webp', 'size_bytes' => 6, 'width' => 10, 'height' => 10,
            'checksum' => hash('sha256', 'master'), 'status' => MediaStatus::Ready,
        ]);
        ProductMedia::query()->create([
            'tenant_id' => $tenant->getKey(), 'product_id' => $record['product']->getKey(),
            'media_asset_id' => $asset->getKey(), 'is_primary' => true, 'position' => 0, 'alt_text' => 'Product image',
        ]);
        Storage::disk('local')->put('private/thumb.webp', 'image!');
        MediaDerivative::query()->create([
            'tenant_id' => $tenant->getKey(), 'media_asset_id' => $asset->getKey(),
            'variant' => MediaDerivativeVariant::Thumbnail, 'disk' => 'local', 'storage_key' => 'private/thumb.webp',
            'mime_type' => 'image/webp', 'width' => 10, 'height' => 10, 'size_bytes' => 6, 'checksum' => $checksum,
        ]);

        $this->get("/tienda/media-store/media/{$checksum}/thumbnail")
            ->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'no-store, private');
        $this->get("/tienda/other-store/media/{$checksum}/thumbnail")->assertNotFound();
        $this->get('/tienda/media-store/media/'.str_repeat('a', 64).'/thumbnail')->assertNotFound();
    }

    public function test_catalog_query_count_is_bounded_for_a_full_page(): void
    {
        $tenant = $this->createTenant();
        $store = $this->createStore($tenant);
        $this->publish($store, 'query-store');
        foreach (range(1, 12) as $index) {
            $this->createPublishedProduct($store, ['name' => "Bounded {$index}", 'slug' => "bounded-{$index}"]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/tienda/query-store/productos')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(35, $count, "Catalog issued {$count} queries.");
    }
}
