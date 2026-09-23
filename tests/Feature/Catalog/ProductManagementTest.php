<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Tenancy\Application\TenantContext;
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

    public function test_product_edit_page_lists_variants_and_options(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant, ['sku' => 'DEFAULT-SKU']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.products.edit', $product))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/Edit')
                ->has('product.variants', 1)
                ->where('product.variants.0.sku', 'DEFAULT-SKU')
                ->has('product.options', 0));
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
}
