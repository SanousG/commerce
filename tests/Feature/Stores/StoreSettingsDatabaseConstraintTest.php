<?php

namespace Tests\Feature\Stores;

use App\Models\User;
use App\Modules\Stores\Domain\Models\StoreSettings;
use App\Modules\Inventory\Application\CreateDefaultInventoryLocation;
use Illuminate\Database\QueryException;

class StoreSettingsDatabaseConstraintTest extends StoreFeatureTestCase
{
    public function test_database_rejects_duplicate_settings_for_one_store(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        StoreSettings::create(['tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'country_code' => 'PE', 'social_links' => StoreSettings::DEFAULT_SOCIAL_LINKS, 'primary_color' => '#17211B']);

        $this->expectException(QueryException::class);
        StoreSettings::create(['tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'country_code' => 'PE', 'social_links' => StoreSettings::DEFAULT_SOCIAL_LINKS, 'primary_color' => '#17211B']);
    }

    public function test_database_rejects_cross_tenant_store_reference(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $storeB = $this->createStore($tenantB);

        $this->expectException(QueryException::class);
        StoreSettings::create(['tenant_id' => $tenantA->getKey(), 'store_id' => $storeB->getKey(), 'country_code' => 'PE', 'social_links' => StoreSettings::DEFAULT_SOCIAL_LINKS, 'primary_color' => '#17211B']);
    }

    public function test_database_rejects_invalid_country_color_and_phone_shape(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);

        $this->expectException(QueryException::class);
        StoreSettings::create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'country_code' => 'peru',
            'contact_phone' => 'unsafe', 'social_links' => StoreSettings::DEFAULT_SOCIAL_LINKS, 'primary_color' => 'black',
        ]);
    }

    public function test_database_rejects_enabling_pickup_without_complete_address(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $location = app(CreateDefaultInventoryLocation::class)->handle($tenant);

        $this->expectException(QueryException::class);
        StoreSettings::create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(), 'country_code' => 'PE',
            'social_links' => StoreSettings::DEFAULT_SOCIAL_LINKS, 'primary_color' => '#17211B',
            'pickup_enabled' => true, 'pickup_inventory_location_id' => $location->getKey(),
        ]);
    }
}
