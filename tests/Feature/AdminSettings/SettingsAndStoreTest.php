<?php

namespace Tests\Feature\AdminSettings;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Stores\Application\StoreContext;
use Inertia\Testing\AssertableInertia as Assert;

class SettingsAndStoreTest extends AdminSettingsFeatureTestCase
{
    public function test_owner_and_admin_can_view_settings_but_member_cannot(): void
    {
        $tenant = $this->createTenant();
        foreach ([SystemRole::Owner, SystemRole::Admin] as $role) {
            $user = User::factory()->create();
            $this->createMembership($user, $tenant, $role);
            $this->actingAs($user)->withSession($this->tenantSession($tenant))
                ->get(route('admin.settings.index'))->assertOk();
        }

        $member = User::factory()->create();
        $this->createMembership($member, $tenant, SystemRole::Member);
        $this->actingAs($member)->withSession($this->tenantSession($tenant))
            ->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_company_name_can_be_updated_without_accepting_tenant_id(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $owner = $this->ownerIn($tenant);

        $this->actingAs($owner)->withSession($this->tenantSession($tenant))
            ->patch(route('admin.settings.general.update'), ['name' => 'Updated Company', 'tenant_id' => $other->getKey()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->getKey(), 'name' => 'Updated Company']);
        $this->assertDatabaseMissing('tenants', ['id' => $other->getKey(), 'name' => 'Updated Company']);
    }

    public function test_store_settings_are_tenant_scoped_and_validate_timezone(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $store = $this->storeFor($tenant);
        $otherStore = $this->storeFor($other);

        $this->actingAs($owner)->withSession($this->tenantSession($tenant))
            ->patch(route('admin.stores.update', $store), [
                'name' => 'Lima Store', 'status' => 'ACTIVE', 'currency' => 'USD',
                'timezone' => 'Invalid/Timezone', 'locale' => 'es_PE',
            ])->assertSessionHasErrors('timezone');

        $this->patch(route('admin.stores.update', $otherStore), [
            'name' => 'Intrusion', 'status' => 'ACTIVE', 'currency' => 'USD',
            'timezone' => 'America/Lima', 'locale' => 'es_PE',
        ])->assertNotFound();

        $this->assertDatabaseMissing('stores', ['id' => $otherStore->getKey(), 'name' => 'Intrusion']);
    }

    public function test_deactivating_selected_store_clears_store_context(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $store = $this->storeFor($tenant);

        $this->actingAs($owner)->withSession($this->tenantSession($tenant) + [StoreContext::SESSION_KEY => $store->getKey()])
            ->patch(route('admin.stores.update', $store), [
                'name' => $store->name, 'status' => 'INACTIVE', 'currency' => 'PEN',
                'timezone' => 'America/Lima', 'locale' => 'es_PE',
            ])->assertRedirect(route('admin.stores.index'))->assertSessionMissing(StoreContext::SESSION_KEY);
    }

    public function test_settings_hub_derives_current_store_localization(): void
    {
        $tenant = $this->createTenant();
        $owner = $this->ownerIn($tenant);
        $store = $this->storeFor($tenant, ['currency' => 'USD', 'timezone' => 'America/New_York', 'locale' => 'en_US']);

        $this->actingAs($owner)->withSession($this->tenantSession($tenant) + [StoreContext::SESSION_KEY => $store->getKey()])
            ->get(route('admin.settings.index'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/Index')
            ->where('store.currency', 'USD')
            ->where('store.timezone', 'America/New_York')
            ->where('setup.localization.status', 'Configured'));
    }
}
