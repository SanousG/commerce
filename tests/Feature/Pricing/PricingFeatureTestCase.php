<?php

namespace Tests\Feature\Pricing;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Pricing\Application\CreateDefaultPriceList;
use App\Modules\Pricing\Application\CreatePriceList;
use App\Modules\Pricing\Domain\Models\PriceList;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class PricingFeatureTestCase extends TestCase
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

    protected function createVariant(Tenant $tenant, string $sku = ''): ProductVariant
    {
        $product = app(CreateProduct::class)->handle($tenant, [
            'name' => fake()->unique()->words(3, true),
            'sku' => $sku !== '' ? $sku : 'SKU-'.Str::upper(Str::random(10)),
        ]);

        return $product->variants()->sole();
    }

    protected function createPriceList(Tenant $tenant, array $attributes = []): PriceList
    {
        return app(CreatePriceList::class)->handle($tenant, [
            'name' => $attributes['name'] ?? 'Wholesale',
            'code' => $attributes['code'] ?? 'WHOLESALE-'.Str::upper(Str::random(5)),
            'currency' => $attributes['currency'] ?? 'USD',
        ]);
    }

    protected function priceListPayload(string $code = 'WHOLESALE'): array
    {
        return ['name' => 'Wholesale', 'code' => $code, 'currency' => 'USD'];
    }
}
