<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Catalog\Domain\Enums\CategoryStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class CategoryTest extends CatalogFeatureTestCase
{
    public function test_owner_and_admin_manage_categories_while_member_only_views(): void
    {
        $admin = User::factory()->create();
        $tenant = $this->createTenantFor($admin, SystemRole::Admin);
        $this->actingAs($admin)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.categories.store'), ['name' => 'Clothing', 'position' => 1])
            ->assertRedirect(route('admin.categories.index'));
        $category = $tenant->categories()->sole();
        $this->put(route('admin.categories.update', $category), [
            'name' => 'Apparel', 'status' => 'ACTIVE', 'parent_id' => null, 'position' => 2,
        ])->assertRedirect();
        $this->patch(route('admin.categories.archive', $category))->assertRedirect();
        $this->assertSame(CategoryStatus::Archived, $category->fresh()->status);

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $this->actingAs($member)->withSession([TenantContext::SESSION_KEY => $memberTenant->getKey()])
            ->get(route('admin.categories.index'))->assertOk();
        $this->post(route('admin.categories.store'), ['name' => 'Forbidden'])->assertForbidden();
    }

    public function test_category_list_is_tenant_scoped_and_paginated(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->createCategory($tenant, ['name' => 'Visible']);
        $this->createCategory($this->createTenantFor(User::factory()->create()), ['name' => 'Secret']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.data', 1)
                ->where('categories.data.0.name', 'Visible')
                ->has('categories.links'));
    }

    public function test_category_slug_is_generated_per_tenant_and_database_rejects_duplicate(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $first = $this->createCategory($tenantA, ['name' => 'Men']);
        $second = $this->createCategory($tenantA, ['name' => 'Men']);
        $other = $this->createCategory($tenantB, ['name' => 'Men']);
        $this->assertSame('men', $first->slug);
        $this->assertSame('men-2', $second->slug);
        $this->assertSame('men', $other->slug);

        $this->expectException(QueryException::class);
        Category::create([
            'tenant_id' => $tenantA->getKey(), 'name' => 'Duplicate', 'slug' => 'men',
            'status' => CategoryStatus::Active, 'position' => 0,
        ]);
    }

    public function test_category_parent_must_share_tenant_and_self_parent_is_rejected(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor(User::factory()->create());
        $categoryA = $this->createCategory($tenantA);
        $categoryB = $this->createCategory($tenantB);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);

        $this->post(route('admin.categories.store'), ['name' => 'Bad child', 'parent_id' => $categoryB->getKey()])
            ->assertSessionHasErrors('parent_id');
        $this->put(route('admin.categories.update', $categoryA), [
            'name' => $categoryA->name, 'status' => 'ACTIVE', 'parent_id' => $categoryA->getKey(), 'position' => 0,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_category_cannot_be_moved_below_its_descendant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $parent = $this->createCategory($tenant, ['name' => 'Parent']);
        $child = $this->createCategory($tenant, ['name' => 'Child', 'parent_id' => $parent->getKey()]);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->put(route('admin.categories.update', $parent), [
                'name' => 'Parent', 'status' => 'ACTIVE', 'parent_id' => $child->getKey(), 'position' => 0,
            ])->assertSessionHasErrors('parent_id');

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_product_supports_multiple_same_tenant_categories(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $one = $this->createCategory($tenant);
        $two = $this->createCategory($tenant);
        $product = $this->createProduct($tenant, ['categories' => [$one->getKey(), $two->getKey()]]);

        $this->assertEqualsCanonicalizing([$one->getKey(), $two->getKey()], $product->categories()->pluck('categories.id')->all());
    }

    public function test_database_rejects_cross_tenant_category_product_pivot(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $product = $this->createProduct($tenantA);
        $category = $this->createCategory($tenantB);

        $this->expectException(QueryException::class);
        DB::table('category_product')->insert([
            'tenant_id' => $tenantA->getKey(),
            'product_id' => $product->getKey(),
            'category_id' => $category->getKey(),
        ]);
    }

    public function test_cross_tenant_category_edit_returns_not_found(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $foreign = $this->createCategory($this->createTenantFor(User::factory()->create()));

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.categories.edit', $foreign))->assertNotFound();
    }
}
