<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Permission;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Catalog\Application\CreateProductOption;
use App\Modules\Catalog\Application\SaveVariant;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VariantOptionTest extends CatalogFeatureTestCase
{
    public function test_variant_sku_and_non_null_barcode_are_unique_per_tenant(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $this->createProduct($tenantA, ['sku' => 'SHARED', 'barcode' => 'BAR-1']);
        $this->createProduct($tenantB, ['sku' => 'SHARED', 'barcode' => 'BAR-1']);

        $otherProduct = $this->createProduct($tenantA, ['sku' => 'OTHER']);

        $this->expectException(QueryException::class);
        ProductVariant::create([
            'tenant_id' => $tenantA->getKey(),
            'product_id' => $otherProduct->getKey(),
            'sku' => 'shared',
            'barcode' => null,
            'name' => 'Case duplicate',
            'is_default' => false,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_barcode_is_optional_and_duplicate_barcode_is_rejected_by_http_validation(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant, ['barcode' => 'CODE-1']);
        app(SaveVariant::class)->create($tenant, $product, [
            'sku' => 'NULL-BARCODE', 'barcode' => null, 'status' => 'ACTIVE', 'is_default' => false,
        ]);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.products.variants.store', $product), [
                'name' => 'Duplicate', 'sku' => 'NEW-SKU', 'barcode' => 'CODE-1',
                'status' => 'ACTIVE', 'is_default' => false, 'option_value_ids' => [],
            ])->assertSessionHasErrors('barcode');
    }

    public function test_variant_requires_sku(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.products.variants.store', $product), [
                'sku' => '', 'status' => 'ACTIVE', 'is_default' => false,
            ])->assertSessionHasErrors('sku');
    }

    public function test_product_has_exactly_one_active_default_variant_and_default_can_be_swapped(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.products.variants.store', $product), [
                'name' => 'Blue / M', 'sku' => 'BLUE-M', 'barcode' => null,
                'status' => 'ACTIVE', 'is_default' => true, 'option_value_ids' => [],
            ])->assertRedirect();

        $this->assertSame(1, $product->variants()->where('is_default', true)->count());
        $this->assertSame('BLUE-M', $product->variants()->where('is_default', true)->sole()->sku);

        $this->expectException(QueryException::class);
        $product->variants()->where('is_default', true)->update(['is_default' => false]);
    }

    public function test_archived_variant_cannot_be_default(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $product = $this->createProduct($tenant);

        $this->expectException(ValidationException::class);
        app(SaveVariant::class)->create($tenant, $product, [
            'sku' => 'ARCHIVED-DEFAULT',
            'status' => ProductVariantStatus::Archived->value,
            'is_default' => true,
        ]);
    }

    public function test_options_values_and_variant_associations_are_structured(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $product = $this->createProduct($tenant);
        $color = app(CreateProductOption::class)->handle($tenant, $product, [
            'name' => 'Color', 'values' => ['Black', 'White'],
        ]);
        $size = app(CreateProductOption::class)->handle($tenant, $product, [
            'name' => 'Size', 'values' => ['S', 'M'],
        ]);
        $variant = app(SaveVariant::class)->create($tenant, $product, [
            'name' => 'Black / M', 'sku' => 'BLACK-M', 'status' => 'ACTIVE', 'is_default' => false,
            'option_value_ids' => [$color->values()->where('value', 'Black')->sole()->getKey(), $size->values()->where('value', 'M')->sole()->getKey()],
        ]);

        $this->assertEqualsCanonicalizing(['Black', 'M'], $variant->optionValues()->pluck('value')->all());
        $this->assertTrue($product->options()->whereKey($color)->exists());
    }

    public function test_variant_rejects_values_from_another_product_and_duplicate_option_values(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $productA = $this->createProduct($tenant);
        $productB = $this->createProduct($tenant);
        $optionA = app(CreateProductOption::class)->handle($tenant, $productA, ['name' => 'Color', 'values' => ['Black', 'White']]);
        $optionB = app(CreateProductOption::class)->handle($tenant, $productB, ['name' => 'Size', 'values' => ['M']]);
        $black = $optionA->values()->where('value', 'Black')->sole();
        $white = $optionA->values()->where('value', 'White')->sole();
        $foreign = $optionB->values()->sole();
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $payload = ['sku' => 'BAD-1', 'status' => 'ACTIVE', 'is_default' => false];
        $this->post(route('admin.products.variants.store', $productA), [...$payload, 'option_value_ids' => [$foreign->getKey()]])
            ->assertSessionHasErrors('option_value_ids');
        $this->post(route('admin.products.variants.store', $productA), [...$payload, 'option_value_ids' => [$black->getKey(), $white->getKey()]])
            ->assertSessionHasErrors('option_value_ids');
    }

    public function test_database_rejects_cross_product_variant_option_pivot(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $productA = $this->createProduct($tenant);
        $productB = $this->createProduct($tenant);
        $option = app(CreateProductOption::class)->handle($tenant, $productA, ['name' => 'Color', 'values' => ['Black']]);
        $value = $option->values()->sole();
        $variantB = $productB->variants()->sole();

        $this->expectException(QueryException::class);
        DB::table('product_variant_option_values')->insert([
            'product_id' => $productB->getKey(),
            'product_variant_id' => $variantB->getKey(),
            'product_option_id' => $option->getKey(),
            'product_option_value_id' => $value->getKey(),
        ]);
    }

    public function test_cross_tenant_variant_cannot_be_modified(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $foreignProduct = $this->createProduct($this->createTenantFor(User::factory()->create()));
        $foreignVariant = $foreignProduct->variants()->sole();

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->put(route('admin.products.variants.update', [$foreignProduct, $foreignVariant]), [
                'name' => 'Attack', 'sku' => 'ATTACK', 'barcode' => null,
                'status' => 'ACTIVE', 'is_default' => true, 'option_value_ids' => [],
            ])->assertNotFound();
    }

    public function test_adding_variant_requires_product_update_permission(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $product = $this->createProduct($tenant);
        Role::query()->system(SystemRole::Owner)->sole()->permissions()->detach(
            Permission::query()->where('key', PermissionKey::ProductsUpdate)->sole(),
        );

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.products.variants.store', $product), [
                'name' => 'Forbidden', 'sku' => 'FORBIDDEN', 'barcode' => null,
                'status' => 'ACTIVE', 'is_default' => false, 'option_value_ids' => [],
            ])->assertForbidden();
    }
}
