<?php

namespace Tests\Feature\Listings;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Application\SaveVariant;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Listings\Application\CreateListing;
use App\Modules\Listings\Domain\Models\StoreListing;
use App\Modules\Pricing\Application\CreateDefaultPriceList;
use App\Modules\Pricing\Application\CreatePriceList;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Promotions\Application\CreatePromotion;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Stores\Application\StoreContext;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class ListingsFeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createTenantFor(User $user, SystemRole $role = SystemRole::Owner): Tenant
    {
        $tenant = Tenant::create([
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(),
            'status' => TenantStatus::Active,
        ]);
        TenantMember::create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->system($role)->sole()->getKey(),
            'status' => TenantMemberStatus::Active,
            'joined_at' => now(),
        ]);
        app(CreateDefaultPriceList::class)->handle($tenant);

        return $tenant;
    }

    protected function createStore(Tenant $tenant, array $attributes = []): Store
    {
        $name = $attributes['name'] ?? fake()->unique()->company();

        return Store::create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'slug' => $attributes['slug'] ?? Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => $attributes['status'] ?? StoreStatus::Active,
            'currency' => $attributes['currency'] ?? 'PEN',
            'timezone' => 'America/Lima',
            'locale' => 'es_PE',
            'default_price_list_id' => $attributes['default_price_list_id'] ?? null,
        ]);
    }

    protected function createProduct(Tenant $tenant, array $attributes = []): Product
    {
        $product = app(CreateProduct::class)->handle($tenant, [
            'name' => $attributes['name'] ?? fake()->unique()->words(3, true),
            'sku' => $attributes['sku'] ?? 'LIST-'.Str::upper(Str::random(10)),
        ]);
        $product->update(['status' => $attributes['status'] ?? ProductStatus::Active]);

        return $product->refresh();
    }

    protected function addVariant(
        Tenant $tenant,
        Product $product,
        ProductVariantStatus $status = ProductVariantStatus::Active,
        ?string $sku = null,
    ): ProductVariant {
        return app(SaveVariant::class)->create($tenant, $product, [
            'sku' => $sku ?? 'LIST-VAR-'.Str::upper(Str::random(8)),
            'name' => 'Additional variant',
            'status' => $status->value,
            'is_default' => false,
            'option_value_ids' => [],
        ]);
    }

    protected function createListing(Store $store, Product $product, array $attributes = []): StoreListing
    {
        return app(CreateListing::class)->handle($store->tenant()->firstOrFail(), $store, [
            'product_id' => $product->getKey(),
            'storefront_slug' => $attributes['storefront_slug'] ?? null,
            'price_list_id' => $attributes['price_list_id'] ?? null,
        ]);
    }

    protected function createPriceList(Tenant $tenant, string $name): PriceList
    {
        return app(CreatePriceList::class)->handle($tenant, [
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '_')).'-'.Str::upper(Str::random(5)),
            'currency' => 'PEN',
        ]);
    }

    protected function priceVariant(Tenant $tenant, PriceList $list, ProductVariant $variant, string $amount): void
    {
        app(SetVariantPrice::class)->handle($tenant, $list, $variant, $amount);
    }

    protected function createPromotion(Tenant $tenant, array $attributes = []): Promotion
    {
        return app(CreatePromotion::class)->handle($tenant, array_replace([
            'name' => 'Listing preview sale',
            'internal_code' => 'LISTING-PREVIEW',
            'description' => null,
            'status' => 'ACTIVE',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => '25',
            'currency' => null,
            'starts_at' => null,
            'ends_at' => null,
            'priority' => 10,
            'scope' => 'ALL',
            'product_ids' => [],
            'category_ids' => [],
            'variant_ids' => [],
        ], $attributes));
    }

    protected function useContext(User $user, Tenant $tenant, Store $store): void
    {
        $this->actingAs($user)->withSession([
            TenantContext::SESSION_KEY => $tenant->getKey(),
            StoreContext::SESSION_KEY => $store->getKey(),
        ]);
    }
}
