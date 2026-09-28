<?php

namespace Tests\Feature\AdminSettings;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Stores\Domain\Enums\StoreStatus;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Tests\Feature\Access\AccessFeatureTestCase;

abstract class AdminSettingsFeatureTestCase extends AccessFeatureTestCase
{
    protected function ownerIn(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $this->createMembership($user, $tenant, SystemRole::Owner);

        return $user;
    }

    protected function storeFor(Tenant $tenant, array $attributes = []): Store
    {
        return Store::create(array_merge([
            'tenant_id' => $tenant->getKey(),
            'name' => 'Main Store',
            'slug' => fake()->unique()->slug(),
            'status' => StoreStatus::Active,
            'currency' => 'PEN',
            'timezone' => 'America/Lima',
            'locale' => 'es_PE',
        ], $attributes));
    }

    protected function tenantSession(Tenant $tenant): array
    {
        return [TenantContext::SESSION_KEY => $tenant->getKey()];
    }
}
