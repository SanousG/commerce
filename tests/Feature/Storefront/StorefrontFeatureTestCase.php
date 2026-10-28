<?php

namespace Tests\Feature\Storefront;

use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLevel;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Inventory\Application\CreateDefaultInventoryLocation;
use App\Modules\Listings\Application\CreateListing;
use App\Modules\Listings\Application\PublishListing;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Pricing\Application\CreateDefaultPriceList;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Stores\Domain\Models\StorefrontPublication;
use App\Modules\Stores\Domain\Models\StoreSettings;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class StorefrontFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'storefront.development_route_enabled' => true,
            'storefront.development_route_environments' => ['local', 'testing'],
            'storefront.development_hosts' => ['localhost', '127.0.0.1'],
        ]);
    }

    protected function createTenant(TenantStatus $status = TenantStatus::Active): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
            'status' => $status,
        ]);
        app(CreateDefaultPriceList::class)->handle($tenant);
        app(CreateDefaultInventoryLocation::class)->handle($tenant);

        return $tenant;
    }

    protected function createStore(
        Tenant $tenant,
        StoreStatus $status = StoreStatus::Active,
        array $attributes = [],
    ): Store {
        $store = Store::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $attributes['name'] ?? fake()->unique()->company(),
            'slug' => $attributes['slug'] ?? 'store-'.Str::lower(Str::random(8)),
            'status' => $status,
            'currency' => $attributes['currency'] ?? 'PEN',
            'timezone' => $attributes['timezone'] ?? 'America/Lima',
            'locale' => $attributes['locale'] ?? 'es_PE',
        ]);

        StoreSettings::query()->create([
            'tenant_id' => $tenant->getKey(),
            'store_id' => $store->getKey(),
            'display_name' => $attributes['display_name'] ?? null,
            'short_description' => $attributes['short_description'] ?? null,
            'contact_email' => $attributes['contact_email'] ?? null,
            'contact_phone' => $attributes['contact_phone'] ?? null,
            'country_code' => 'PE',
            'social_links' => StoreSettings::DEFAULT_SOCIAL_LINKS,
            'primary_color' => $attributes['primary_color'] ?? '#17211B',
        ]);

        return $store;
    }

    protected function publish(Store $store, string $handle): StorefrontPublication
    {
        return StorefrontPublication::query()->create([
            'tenant_id' => $store->tenant_id,
            'store_id' => $store->getKey(),
            'public_handle' => $handle,
            'status' => StorefrontPublicationStatus::Published,
            'published_at' => now(),
        ]);
    }

    /** @return array{product: Product, variant: ProductVariant, listing: StoreListing, priceList: PriceList} */
    protected function createPublishedProduct(Store $store, array $attributes = []): array
    {
        $tenant = $store->tenant()->firstOrFail();
        $product = app(CreateProduct::class)->handle($tenant, [
            'name' => $attributes['name'] ?? 'Essential product '.Str::random(6),
            'sku' => $attributes['sku'] ?? 'SF-'.Str::upper(Str::random(10)),
        ]);
        $product->update([
            'status' => $attributes['product_status'] ?? ProductStatus::Active,
            'description' => $attributes['description'] ?? 'Public product description.',
        ]);
        $variant = $product->defaultVariant()->firstOrFail();
        $priceList = $tenant->priceLists()->where('is_default', true)->firstOrFail();
        app(SetVariantPrice::class)->handle($tenant, $priceList, $variant, $attributes['price'] ?? '129.0000');
        $listing = app(CreateListing::class)->handle($tenant, $store, [
            'product_id' => $product->getKey(),
            'storefront_slug' => $attributes['slug'] ?? Str::slug($product->name),
        ]);
        app(PublishListing::class)->handle($tenant, $store, $listing);

        if (array_key_exists('stock', $attributes)) {
            $location = $tenant->inventoryLocations()->where('is_default', true)->firstOrFail();
            InventoryLevel::query()->create([
                'tenant_id' => $tenant->getKey(), 'inventory_location_id' => $location->getKey(),
                'product_variant_id' => $variant->getKey(), 'on_hand' => $attributes['stock'], 'reserved' => 0,
            ]);
        }

        return compact('product', 'variant', 'listing', 'priceList');
    }
}
