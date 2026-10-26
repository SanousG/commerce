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
        $this->actingAs($admin)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->get(route('admin.categories.create'))->assertOk();
        $this
            ->post(route('admin.categories.store'), ['name' => 'Clothing', 'position' => 1])
            ->assertRedirect(route('admin.categories.index'));
        $category = $tenant->categories()->sole();
        $this->get(route('admin.categories.edit', $category))->assertOk();
        $this->put(route('admin.categories.update', $category), [
            'name' => 'Apparel', 'status' => 'ACTIVE', 'parent_id' => null, 'position' => 2,
        ])->assertRedirect();
        $this->patch(route('admin.categories.archive', $category))->assertRedirect();
        $this->assertSame(CategoryStatus::Archived, $category->fresh()->status);

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $memberCategory = $this->createCategory($memberTenant);
        $this->actingAs($member)->withSession([TenantContext::SESSION_KEY => $memberTenant->getKey()])
            ->get(route('admin.categories.index'))->assertOk();
        $this->post(route('admin.categories.store'), ['name' => 'Forbidden'])->assertForbidden();
        $this->get(route('admin.categories.edit', $memberCategory))->assertOk();
        $this->put(route('admin.categories.update', $memberCategory), [
            'name' => 'Forbidden update',
            'status' => 'ACTIVE',
            'parent_id' => null,
            'position' => 0,
        ])->assertForbidden();
    }

    public function test_category_list_is_tenant_scoped_and_paginated(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        foreach (range(1, 21) as $index) {
            $this->createCategory($tenant, [
                'name' => sprintf('Visible %02d', $index),
                'position' => $index,
            ]);
        }
        $this->createCategory($this->createTenantFor(User::factory()->create()), ['name' => 'Secret']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.data', 20)
                ->where('categories.data.0.name', 'Visible 01')
                ->where('categories.per_page', 20)
                ->where('categories.total', 21)
                ->has('categories.links'));
    }

    public function test_category_list_exposes_flat_parent_status_position_and_product_count(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $parent = $this->createCategory($tenant, ['name' => 'Industrial Safety', 'position' => 1]);
        $child = $this->createCategory($tenant, [
            'name' => 'Eye Protection',
            'parent_id' => $parent->getKey(),
            'position' => 4,
        ]);
        $child->update(['status' => CategoryStatus::Inactive]);
        $this->createProduct($tenant, ['name' => 'Safety glasses', 'categories' => [$child->getKey()]]);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories.data', 2)
                ->where('categories.data.0.name', 'Industrial Safety')
                ->where('categories.data.0.parent', null)
                ->where('categories.data.0.position', 1)
                ->where('categories.data.0.status', 'ACTIVE')
                ->where('categories.data.0.products_count', 0)
                ->where('categories.data.1.name', 'Eye Protection')
                ->where('categories.data.1.parent', 'Industrial Safety')
                ->where('categories.data.1.position', 4)
                ->where('categories.data.1.status', 'INACTIVE')
                ->where('categories.data.1.products_count', 1));
    }

    public function test_category_creation_validates_name_and_creates_active_root_with_defaults(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->post(route('admin.categories.store'), ['name' => '   '])
            ->assertSessionHasErrors('name');
        $this->assertFalse($tenant->categories()->exists());

        $this->post(route('admin.categories.store'), ['name' => '  Thermal Paper  '])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('status', 'Categoría creada.');

        $category = $tenant->categories()->sole();
        $this->assertSame('Thermal Paper', $category->name);
        $this->assertSame('thermal-paper', $category->slug);
        $this->assertSame(CategoryStatus::Active, $category->status);
        $this->assertNull($category->parent_id);
        $this->assertSame(0, $category->position);
    }

    public function test_category_update_changes_fields_and_preserves_slug_and_product_associations(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $newParent = $this->createCategory($tenant, ['name' => 'Safety', 'position' => 1]);
        $category = $this->createCategory($tenant, ['name' => 'Glasses', 'position' => 2]);
        $product = $this->createProduct($tenant, ['categories' => [$category->getKey()]]);
        $originalSlug = $category->slug;
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->from(route('admin.categories.edit', $category))
            ->put(route('admin.categories.update', $category), [
                'name' => '  Eye Protection  ',
                'status' => 'INACTIVE',
                'parent_id' => $newParent->getKey(),
                'position' => 7,
            ])
            ->assertRedirect(route('admin.categories.edit', $category))
            ->assertSessionHas('status', 'Categoría actualizada.');

        $category->refresh();
        $this->assertSame('Eye Protection', $category->name);
        $this->assertSame($originalSlug, $category->slug);
        $this->assertSame(CategoryStatus::Inactive, $category->status);
        $this->assertTrue($category->parent->is($newParent));
        $this->assertSame(7, $category->position);
        $this->assertTrue($category->products()->whereKey($product->getKey())->exists());
    }

    public function test_edit_page_excludes_invalid_parent_candidates_and_returns_real_statuses(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $category = $this->createCategory($tenant, ['name' => 'Clothing']);
        $child = $this->createCategory($tenant, ['name' => 'Menswear', 'parent_id' => $category->getKey()]);
        $this->createCategory($tenant, ['name' => 'Shirts', 'parent_id' => $child->getKey()]);
        $inactive = $this->createCategory($tenant, ['name' => 'Accessories']);
        $inactive->update(['status' => CategoryStatus::Inactive]);
        $this->createCategory($tenant, ['name' => 'Footwear']);
        $archived = $this->createCategory($tenant, ['name' => 'Legacy']);
        $archived->update(['status' => CategoryStatus::Archived]);
        $this->createCategory($this->createTenantFor(User::factory()->create()), ['name' => 'Foreign']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.categories.edit', $category))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/categories/Edit')
                ->where('category.id', $category->getKey())
                ->where('category.slug', $category->slug)
                ->where('statuses', ['ACTIVE', 'INACTIVE', 'ARCHIVED'])
                ->has('parents', 2)
                ->where('parents.0.name', 'Accessories')
                ->where('parents.2.name', 'Footwear'));
    }

    public function test_archived_category_can_be_reactivated_through_the_existing_update_contract(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $category = $this->createCategory($tenant, ['name' => 'Legacy']);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $this->patch(route('admin.categories.archive', $category))->assertRedirect(route('admin.categories.index'));

        $this->put(route('admin.categories.update', $category), [
            'name' => 'Restored',
            'status' => 'ACTIVE',
            'parent_id' => null,
            'position' => 0,
        ])->assertRedirect();

        $this->assertSame(CategoryStatus::Active, $category->fresh()->status);
    }

    public function test_create_page_lists_eligible_tenant_parents_and_category_can_be_created_below_one(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $parent = $this->createCategory($tenant, ['name' => 'Industrial Safety', 'position' => 1]);
        $inactive = $this->createCategory($tenant, ['name' => 'Office Supplies', 'position' => 2]);
        $inactive->update(['status' => CategoryStatus::Inactive]);
        $archived = $this->createCategory($tenant, ['name' => 'Legacy', 'position' => 3]);
        $archived->update(['status' => CategoryStatus::Archived]);
        $this->createCategory($this->createTenantFor(User::factory()->create()), ['name' => 'Foreign']);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->get(route('admin.categories.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/categories/Create')
                ->has('parents', 2)
                ->where('parents.0.id', $parent->getKey())
                ->where('parents.0.name', 'Industrial Safety')
                ->where('parents.1.id', $inactive->getKey())
                ->where('parents.1.name', 'Office Supplies'));

        $this->post(route('admin.categories.store'), [
            'name' => 'Eye Protection',
            'parent_id' => $parent->getKey(),
            'position' => 7,
        ])->assertRedirect(route('admin.categories.index'));

        $child = $tenant->categories()->where('name', 'Eye Protection')->sole();
        $this->assertTrue($child->parent->is($parent));
        $this->assertSame(7, $child->position);

        $this->get(route('admin.categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.data.3.name', 'Eye Protection')
                ->where('categories.data.3.parent', 'Industrial Safety'));
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
            ])->assertSessionHasErrors([
                'parent_id' => 'Una categoría no puede anidarse debajo de sí misma.',
            ]);

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
        $this->put(route('admin.categories.update', $foreign), [
            'name' => 'Attack',
            'status' => 'ACTIVE',
            'parent_id' => null,
            'position' => 0,
        ])->assertNotFound();
    }
}
