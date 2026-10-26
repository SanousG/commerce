<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\QueryException;

class ProductCreationTest extends CatalogFeatureTestCase
{
    public function test_owner_creates_tenant_product_with_default_variant_and_ignores_tenant_id(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $other = $this->createTenantFor(User::factory()->create());

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.products.store'), [
                ...$this->productPayload(),
                'tenant_id' => $other->getKey(),
            ])->assertRedirect();

        $product = Product::query()->with('variants')->sole();
        $variant = $product->variants->sole();
        $this->assertSame($tenant->getKey(), $product->tenant_id);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame('mouse-logitech', $product->slug);
        $this->assertSame($tenant->getKey(), $variant->tenant_id);
        $this->assertTrue($variant->is_default);
        $this->assertSame(ProductVariantStatus::Active, $variant->status);
    }

    public function test_admin_can_create_and_member_cannot_create_products(): void
    {
        $admin = User::factory()->create();
        $adminTenant = $this->createTenantFor($admin, SystemRole::Admin);
        $this->actingAs($admin)->withSession([TenantContext::SESSION_KEY => $adminTenant->getKey()])
            ->post(route('admin.products.store'), $this->productPayload('Admin product', 'ADMIN-1'))
            ->assertRedirect();

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $this->actingAs($member)->withSession([TenantContext::SESSION_KEY => $memberTenant->getKey()])
            ->get(route('admin.products.create'))->assertForbidden();
        $this->post(route('admin.products.store'), $this->productPayload('Forbidden', 'NOPE-1'))->assertForbidden();
        $this->assertDatabaseMissing('products', ['tenant_id' => $memberTenant->getKey()]);
    }

    public function test_product_can_be_created_with_multiple_tenant_categories(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $parent = $this->createCategory($tenant, ['name' => 'Clothing']);
        $child = $this->createCategory($tenant, ['name' => 'Shirts', 'parent_id' => $parent->getKey()]);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.products.store'), [
                ...$this->productPayload(),
                'categories' => [$parent->getKey(), $child->getKey()],
            ])->assertRedirect();

        $product = Product::query()->sole();
        $this->assertEqualsCanonicalizing(
            [$parent->getKey(), $child->getKey()],
            $product->categories()->pluck('categories.id')->all(),
        );
        $this->assertDatabaseHas('category_product', [
            'tenant_id' => $tenant->getKey(),
            'product_id' => $product->getKey(),
            'category_id' => $child->getKey(),
        ]);
    }

    public function test_product_can_be_created_without_categories(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.products.store'), [
                ...$this->productPayload('Uncategorized product', 'NO-CATEGORY'),
                'categories' => [],
            ])->assertRedirect();

        $this->assertFalse(Product::query()->sole()->categories()->exists());
    }

    public function test_product_and_default_variant_creation_is_atomic(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $this->createProduct($tenant, ['sku' => 'DUPLICATE']);

        try {
            app(CreateProduct::class)->handle($tenant, $this->productPayload('Must roll back', 'DUPLICATE'));
            $this->fail('Duplicate tenant SKU should fail.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('products', ['name' => 'Must roll back']);
        }
    }

    public function test_product_slugs_collide_safely_per_tenant(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $first = $this->createProduct($tenantA, ['name' => 'Polo Nike']);
        $second = $this->createProduct($tenantA, ['name' => 'Polo Nike']);
        $other = $this->createProduct($tenantB, ['name' => 'Polo Nike']);

        $this->assertSame('polo-nike', $first->slug);
        $this->assertSame('polo-nike-2', $second->slug);
        $this->assertSame('polo-nike', $other->slug);
    }

    public function test_product_rejects_category_from_another_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $foreignCategory = $this->createCategory($this->createTenantFor(User::factory()->create()));

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.products.store'), [
                ...$this->productPayload(),
                'categories' => [$foreignCategory->getKey()],
            ])->assertSessionHasErrors('categories.0');
    }
}
