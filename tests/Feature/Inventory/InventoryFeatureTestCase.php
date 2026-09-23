<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Domain\Models\ProductVariant;
use App\Modules\Inventory\Application\AdjustInventory;
use App\Modules\Inventory\Application\CreateDefaultInventoryLocation;
use App\Modules\Inventory\Application\CreateInventoryLocation;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Pricing\Application\CreateDefaultPriceList;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class InventoryFeatureTestCase extends TestCase
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
        app(CreateDefaultInventoryLocation::class)->handle($tenant);

        return $tenant;
    }

    protected function createVariant(Tenant $tenant, string $sku = '', string $name = ''): ProductVariant
    {
        $product = app(CreateProduct::class)->handle($tenant, [
            'name' => $name !== '' ? $name : fake()->unique()->words(3, true),
            'sku' => $sku !== '' ? $sku : 'INV-'.Str::upper(Str::random(10)),
        ]);

        return $product->variants()->sole();
    }

    protected function createLocation(Tenant $tenant, array $attributes = []): InventoryLocation
    {
        return app(CreateInventoryLocation::class)->handle($tenant, [
            'name' => $attributes['name'] ?? fake()->unique()->city().' Warehouse',
            'code' => $attributes['code'] ?? 'LOC-'.Str::upper(Str::random(8)),
            'status' => $attributes['status'] ?? 'ACTIVE',
        ]);
    }

    protected function stock(
        Tenant $tenant,
        InventoryLocation $location,
        ProductVariant $variant,
        int $quantity,
        ?User $actor = null,
    ): void {
        app(AdjustInventory::class)->handle(
            $tenant,
            $location,
            $variant,
            $quantity,
            $actor ?? User::factory()->create(),
            'Test stock',
        );
    }
}
