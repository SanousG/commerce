<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Application\SaveCategory;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class CatalogFeatureTestCase extends TestCase
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

        return $tenant;
    }

    protected function createProduct(Tenant $tenant, array $attributes = []): Product
    {
        return app(CreateProduct::class)->handle($tenant, [
            'name' => $attributes['name'] ?? fake()->unique()->words(3, true),
            'description' => $attributes['description'] ?? null,
            'sku' => $attributes['sku'] ?? 'SKU-'.Str::upper(Str::random(10)),
            'barcode' => $attributes['barcode'] ?? null,
            'categories' => $attributes['categories'] ?? [],
        ]);
    }

    protected function createCategory(Tenant $tenant, array $attributes = []): Category
    {
        return app(SaveCategory::class)->create($tenant, [
            'name' => $attributes['name'] ?? fake()->unique()->word(),
            'parent_id' => $attributes['parent_id'] ?? null,
            'position' => $attributes['position'] ?? 0,
        ]);
    }

    protected function productPayload(string $name = 'Mouse Logitech', string $sku = 'LOGI-MOUSE-001'): array
    {
        return [
            'name' => $name,
            'description' => 'Wireless mouse',
            'sku' => $sku,
            'barcode' => '',
            'categories' => [],
        ];
    }
}
