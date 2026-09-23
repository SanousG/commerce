<?php

namespace Tests\Feature\Promotions;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Pricing\Application\CreateDefaultPriceList;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Promotions\Application\CreatePromotion;
use App\Modules\Promotions\Domain\Models\Promotion;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class PromotionsFeatureTestCase extends TestCase
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
        return app(CreateProduct::class)->handle($tenant, [
            'name' => fake()->unique()->words(3, true),
            'sku' => $sku !== '' ? $sku : 'PROMO-'.Str::upper(Str::random(10)),
        ])->variants()->sole();
    }

    protected function priceVariant(Tenant $tenant, ProductVariant $variant, string $amount = '100'): void
    {
        app(SetVariantPrice::class)->handle($tenant, $tenant->defaultPriceList()->sole(), $variant, $amount);
    }

    /** @param array<string, mixed> $attributes */
    protected function createPromotion(Tenant $tenant, array $attributes = []): Promotion
    {
        return app(CreatePromotion::class)->handle($tenant, array_replace($this->promotionPayload(), $attributes));
    }

    protected function promotionPayload(): array
    {
        return [
            'name' => 'Summer sale',
            'internal_code' => 'SUMMER',
            'description' => null,
            'status' => 'ACTIVE',
            'discount_type' => 'PERCENTAGE',
            'discount_value' => '20',
            'currency' => null,
            'starts_at' => null,
            'ends_at' => null,
            'priority' => 0,
            'scope' => 'ALL',
            'product_ids' => [],
            'category_ids' => [],
            'variant_ids' => [],
        ];
    }
}
