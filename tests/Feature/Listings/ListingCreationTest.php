<?php

namespace Tests\Feature\Listings;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Listings\Application\CreateListing;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Listings\Domain\Models\StoreListing;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

class ListingCreationTest extends ListingsFeatureTestCase
{
    #[DataProvider('managerRoles')]
    public function test_owner_and_admin_can_create_a_listing_for_the_current_store(SystemRole $role): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, $role);
        $currentStore = $this->createStore($tenant);
        $otherTenant = $this->createTenantFor(User::factory()->create());
        $maliciousStore = $this->createStore($otherTenant);
        $product = $this->createProduct($tenant, ['name' => 'Active Running Shoe']);
        $this->useContext($user, $tenant, $currentStore);

        $this->post(route('admin.listings.store'), [
            'product_id' => $product->getKey(),
            'storefront_slug' => ' Running Shoe ',
            'tenant_id' => $otherTenant->getKey(),
            'store_id' => $maliciousStore->getKey(),
        ])->assertRedirect();

        $listing = StoreListing::query()->sole();
        $this->assertSame($tenant->getKey(), $listing->tenant_id);
        $this->assertSame($currentStore->getKey(), $listing->store_id);
        $this->assertSame(StoreListingStatus::Draft, $listing->status);
        $this->assertSame('running-shoe', $listing->storefront_slug);
    }

    public static function managerRoles(): array
    {
        return [
            'owner' => [SystemRole::Owner],
            'admin' => [SystemRole::Admin],
        ];
    }

    public function test_member_cannot_create_and_cross_tenant_product_id_returns_not_found(): void
    {
        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $memberStore = $this->createStore($memberTenant);
        $memberProduct = $this->createProduct($memberTenant);
        $this->useContext($member, $memberTenant, $memberStore);

        $this->post(route('admin.listings.store'), ['product_id' => $memberProduct->getKey()])
            ->assertForbidden();

        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $foreignProduct = $this->createProduct($foreignTenant);
        $this->useContext($owner, $tenant, $store);

        $this->post(route('admin.listings.store'), [
            'product_id' => $product->getKey(),
            'price_list_id' => $foreignTenant->defaultPriceList()->sole()->getKey(),
        ])->assertSessionHasErrors('price_list_id');
        $this->post(route('admin.listings.store'), ['product_id' => $foreignProduct->getKey()])
            ->assertNotFound();
        $this->assertFalse(StoreListing::query()->exists());
    }

    public function test_only_active_products_can_be_listed(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $draft = $this->createProduct($tenant, ['status' => 'DRAFT']);
        $this->useContext($user, $tenant, $store);

        $this->post(route('admin.listings.store'), ['product_id' => $draft->getKey()])
            ->assertSessionHasErrors('product_id');
        $this->assertFalse(StoreListing::query()->exists());
    }

    public function test_product_and_slug_uniqueness_are_per_store(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $firstStore = $this->createStore($tenant);
        $secondStore = $this->createStore($tenant);
        $product = $this->createProduct($tenant, ['name' => 'Shared Product']);
        $otherProduct = $this->createProduct($tenant, ['name' => 'Other Product']);

        $first = $this->createListing($firstStore, $product, ['storefront_slug' => 'shared']);
        $collision = $this->createListing($firstStore, $otherProduct, ['storefront_slug' => 'shared']);
        $otherStore = $this->createListing($secondStore, $product, ['storefront_slug' => 'shared']);

        $this->assertSame('shared', $first->storefront_slug);
        $this->assertSame('shared-2', $collision->storefront_slug);
        $this->assertSame('shared', $otherStore->storefront_slug);

        $this->expectException(ValidationException::class);
        app(CreateListing::class)->handle($tenant, $firstStore, ['product_id' => $product->getKey()]);
    }

    public function test_creation_snapshots_active_and_inactive_variant_enablement(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $inactive = $this->addVariant($tenant, $product, ProductVariantStatus::Inactive, 'INACTIVE-SKU');

        $listing = $this->createListing($store, $product);
        $rows = $listing->variants()->get()->keyBy('product_variant_id');

        $this->assertTrue($rows->get($product->variants()->where('is_default', true)->sole()->getKey())->is_enabled);
        $this->assertFalse($rows->get($inactive->getKey())->is_enabled);
    }

    public function test_variants_added_later_can_be_configured_by_listing_update(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $listing = $this->createListing($store, $product);
        $original = $product->variants()->sole();
        $later = $this->addVariant($tenant, $product, sku: 'LATER-SKU');
        $this->assertFalse($listing->variants()->where('product_variant_id', $later->getKey())->exists());
        $this->useContext($user, $tenant, $store);

        $this->put(route('admin.listings.update', $listing), [
            'storefront_slug' => $listing->storefront_slug,
            'price_list_id' => null,
            'enabled_variant_ids' => [$original->getKey(), $later->getKey()],
        ])->assertRedirect();

        $this->assertTrue($listing->variants()->where('product_variant_id', $later->getKey())->sole()->is_enabled);
    }
}
