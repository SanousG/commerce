<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Pricing\Application\CreateDefaultPriceList;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class ProductManagementTest extends CatalogFeatureTestCase
{
    public function test_product_list_is_tenant_scoped_searchable_and_paginated(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $other = $this->createTenantFor(User::factory()->create());
        $this->createProduct($tenant, ['name' => 'Visible shirt', 'sku' => 'VISIBLE-SKU']);
        $this->createProduct($other, ['name' => 'Secret shirt', 'sku' => 'SECRET-SKU']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.products.index', ['search' => 'VISIBLE-SKU']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Visible shirt')
                ->has('products.links'));

        $this->get(route('admin.products.index', ['search' => 'Secret']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    }

    public function test_switching_tenant_changes_visible_catalog(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $this->createProduct($tenantA, ['name' => 'Catalog A']);
        $this->createProduct($tenantB, ['name' => 'Catalog B']);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);

        $this->get(route('admin.products.index'))->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.name', 'Catalog A'));
        $this->post(route('admin.tenants.select', $tenantB))->assertRedirect();
        $this->get(route('admin.products.index'))->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.name', 'Catalog B'));
    }

    public function test_product_index_returns_fifteen_rows_per_page(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        foreach (range(1, 16) as $index) {
            $this->createProduct($tenant, ['name' => "Product {$index}", 'sku' => "PAGE-{$index}"]);
        }

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 15)
                ->where('products.total', 16)
                ->where('products.per_page', 15));
    }

    public function test_product_index_query_count_does_not_grow_with_page_volume(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        foreach (range(1, 2) as $index) {
            $this->createProduct($tenant, ['sku' => "BASE-{$index}"]);
        }
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->productIndexQueryCount();
        $baseline = $this->productIndexQueryCount();

        foreach (range(3, 12) as $index) {
            $this->createProduct($tenant, ['sku' => "VOLUME-{$index}"]);
        }

        $this->assertSame($baseline, $this->productIndexQueryCount());
    }

    public function test_owner_updates_product_categories_without_changing_slug(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant, ['name' => 'Original']);
        $categoryA = $this->createCategory($tenant, ['name' => 'One']);
        $categoryB = $this->createCategory($tenant, ['name' => 'Two']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->put(route('admin.products.update', $product), [
                'name' => 'Renamed',
                'description' => 'Updated',
                'status' => ProductStatus::Active->value,
                'categories' => [$categoryA->getKey(), $categoryB->getKey()],
            ])->assertRedirect();

        $this->assertSame('original', $product->fresh()->slug);
        $this->assertEqualsCanonicalizing([$categoryA->getKey(), $categoryB->getKey()], $product->categories()->pluck('categories.id')->all());
    }

    public function test_owner_replaces_and_removes_categories_without_changing_variants_or_prices(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $categoryA = $this->createCategory($tenant, ['name' => 'Original category']);
        $categoryB = $this->createCategory($tenant, ['name' => 'Replacement category']);
        $product = $this->createProduct($tenant, ['categories' => [$categoryA->getKey()]]);
        $variant = $product->variants()->sole();
        $priceList = app(CreateDefaultPriceList::class)->handle($tenant);
        $price = app(SetVariantPrice::class)->handle($tenant, $priceList, $variant, '49.90');
        $variantBefore = $variant->getRawOriginal();
        $priceBefore = $price->getRawOriginal();
        $payload = [
            'name' => $product->name,
            'description' => $product->description,
            'status' => $product->status->value,
        ];

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->put(route('admin.products.update', $product), [
                ...$payload,
                'categories' => [$categoryB->getKey()],
            ])->assertRedirect();

        $this->assertSame([$categoryB->getKey()], $product->categories()->pluck('categories.id')->all());
        $this->assertSame($variantBefore, $variant->fresh()->getRawOriginal());
        $this->assertEquals($priceBefore, $price->fresh()->getRawOriginal());

        $this->put(route('admin.products.update', $product), [
            ...$payload,
            'categories' => [],
        ])->assertRedirect();

        $this->assertFalse($product->categories()->exists());
        $this->assertSame($variantBefore, $variant->fresh()->getRawOriginal());
        $this->assertEquals($priceBefore, $price->fresh()->getRawOriginal());
    }

    public function test_product_update_rejects_category_from_another_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant);
        $foreignCategory = $this->createCategory($this->createTenantFor(User::factory()->create()));

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'description' => $product->description,
                'status' => $product->status->value,
                'categories' => [$foreignCategory->getKey()],
            ])->assertSessionHasErrors('categories.0');

        $this->assertFalse($product->categories()->exists());
    }

    public function test_product_index_displays_assigned_categories(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $category = $this->createCategory($tenant, ['name' => 'Office supplies']);
        $this->createProduct($tenant, ['name' => 'Desk organizer', 'categories' => [$category->getKey()]]);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.name', 'Desk organizer')
                ->where('products.data.0.categories', ['Office supplies']));
    }

    public function test_product_edit_page_lists_variants_and_options(): void
    {
        config(['media.max_image_mb' => 17]);
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant, ['sku' => 'DEFAULT-SKU']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.products.edit', $product))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/Edit')
                ->has('product.variants', 1)
                ->where('product.variants.0.sku', 'DEFAULT-SKU')
                ->where('mediaLimits.max_image_mb', 17)
                ->has('product.options', 0));
    }

    public function test_product_edit_page_returns_current_categories_with_parent_context(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $parent = $this->createCategory($tenant, ['name' => 'Clothing']);
        $child = $this->createCategory($tenant, ['name' => 'Shirts', 'parent_id' => $parent->getKey()]);
        $product = $this->createProduct($tenant, ['categories' => [$child->getKey()]]);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.category_ids', [$child->getKey()])
                ->where('categories.1.name', 'Shirts')
                ->where('categories.1.parent_id', $parent->getKey()));
    }

    public function test_cross_tenant_product_edit_and_archive_return_not_found(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $otherProduct = $this->createProduct($this->createTenantFor(User::factory()->create()));
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->get(route('admin.products.edit', $otherProduct))->assertNotFound();
        $this->put(route('admin.products.update', $otherProduct), [
            'name' => 'Attack', 'status' => 'ACTIVE', 'categories' => [],
        ])->assertNotFound();
        $this->patch(route('admin.products.archive', $otherProduct))->assertNotFound();
    }

    public function test_member_can_view_but_cannot_update_or_archive_products(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user, SystemRole::Member);
        $product = $this->createProduct($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->get(route('admin.products.index'))->assertOk();
        $this->put(route('admin.products.update', $product), [
            'name' => 'No', 'status' => 'ACTIVE', 'categories' => [],
        ])->assertForbidden();
        $this->patch(route('admin.products.archive', $product))->assertForbidden();
    }

    public function test_archive_is_logical_and_preserves_variants(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant);
        $variantId = $product->variants()->sole()->getKey();

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->patch(route('admin.products.archive', $product))->assertRedirect(route('admin.products.index'));

        $this->assertSame(ProductStatus::Archived, $product->fresh()->status);
        $this->assertDatabaseHas('product_variants', ['id' => $variantId]);
    }

    private function productIndexQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get(route('admin.products.index'))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
