<?php

namespace Tests\Feature\Stores;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Media\Domain\Enums\MediaStatus;
use App\Modules\Media\Domain\Enums\MediaType;
use App\Modules\Media\Domain\Models\MediaAsset;
use App\Modules\Stores\Application\GetStoreSettings;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

class StoreSettingsTest extends StoreFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['media.disk' => 'store-settings-media-test']);
        Storage::fake('store-settings-media-test');
    }

    public function test_existing_store_gets_defaults_and_owner_can_read_and_update_settings(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->get(route('admin.stores.settings.show', $store))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/stores/Settings')
                ->where('store.id', $store->getKey())
                ->where('settings.country_code', 'PE')
                ->where('settings.primary_color', '#17211B'));

        $this->patch(route('admin.stores.settings.update', $store), $this->payload($store, [
            'display_name' => 'Tienda Miraflores',
            'contact_email' => 'VENTAS@EXAMPLE.COM',
            'contact_phone' => '+51 987-654-321',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Miraflores',
            'social_links' => ['instagram' => 'https://www.instagram.com/example'],
            'primary_color' => '#abcdef',
            'currency' => 'USD',
        ]))->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('store_settings', [
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(),
            'display_name' => 'Tienda Miraflores', 'contact_email' => 'ventas@example.com',
            'contact_phone' => '+51987654321', 'primary_color' => '#ABCDEF',
        ]);
        $this->assertDatabaseHas('stores', ['id' => $store->getKey(), 'currency' => 'USD']);
    }

    public function test_admin_can_update_but_member_cannot_update_settings(): void
    {
        $admin = User::factory()->create();
        $adminTenant = $this->createTenantFor($admin, SystemRole::Admin);
        $adminStore = $this->createStore($adminTenant);
        $this->settings($adminTenant, $adminStore);
        $this->actingAs($admin)->withSession([TenantContext::SESSION_KEY => $adminTenant->getKey()])
            ->patch(route('admin.stores.settings.update', $adminStore), $this->payload($adminStore, ['display_name' => 'Admin Store']))
            ->assertRedirect();

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $memberStore = $this->createStore($memberTenant);
        $this->settings($memberTenant, $memberStore);
        $this->actingAs($member)->withSession([TenantContext::SESSION_KEY => $memberTenant->getKey()])
            ->patch(route('admin.stores.settings.update', $memberStore), $this->payload($memberStore, ['display_name' => 'Forbidden']))
            ->assertForbidden();

        $this->assertDatabaseMissing('store_settings', ['store_id' => $memberStore->getKey(), 'display_name' => 'Forbidden']);
    }

    public function test_cross_tenant_store_cannot_be_read_or_updated(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $other = User::factory()->create();
        $tenantB = $this->createTenantFor($other);
        $storeB = $this->createStore($tenantB);
        $this->settings($tenantB, $storeB);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);

        $this->get(route('admin.stores.settings.show', $storeB))->assertNotFound();
        $this->patch(route('admin.stores.settings.update', $storeB), $this->payload($storeB, ['display_name' => 'Intrusion']))
            ->assertNotFound();
        $this->assertDatabaseMissing('store_settings', ['store_id' => $storeB->getKey(), 'display_name' => 'Intrusion']);
    }

    public function test_two_stores_in_same_tenant_keep_distinct_settings(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $this->settings($tenant, $storeA);
        $this->settings($tenant, $storeB);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->patch(route('admin.stores.settings.update', $storeA), $this->payload($storeA, ['display_name' => 'Store A']))->assertRedirect();
        $this->patch(route('admin.stores.settings.update', $storeB), $this->payload($storeB, ['display_name' => 'Store B', 'primary_color' => '#123456']))->assertRedirect();

        $this->assertDatabaseHas('store_settings', ['store_id' => $storeA->getKey(), 'display_name' => 'Store A', 'primary_color' => '#17211B']);
        $this->assertDatabaseHas('store_settings', ['store_id' => $storeB->getKey(), 'display_name' => 'Store B', 'primary_color' => '#123456']);
    }

    public function test_branding_accepts_same_tenant_media_rejects_other_tenant_and_blocks_deletion_until_unlinked(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $asset = $this->asset($tenant, $user);
        $other = User::factory()->create();
        $otherTenant = $this->createTenantFor($other);
        $otherAsset = $this->asset($otherTenant, $other);
        $this->settings($tenant, $store);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->patch(route('admin.stores.settings.update', $store), $this->payload($store, ['logo_media_asset_id' => $asset->getKey()]))->assertRedirect();
        $this->assertDatabaseHas('store_settings', ['store_id' => $store->getKey(), 'logo_media_asset_id' => $asset->getKey()]);
        $this->deleteJson(route('admin.media.destroy', $asset))->assertConflict();
        Storage::disk('store-settings-media-test')->assertExists($asset->storage_key);

        $this->patch(route('admin.stores.settings.update', $store), $this->payload($store, ['logo_media_asset_id' => null]))->assertRedirect();
        $this->delete(route('admin.media.destroy', $asset))->assertRedirect();
        Storage::disk('store-settings-media-test')->assertMissing($asset->storage_key);

        $this->patch(route('admin.stores.settings.update', $store), $this->payload($store, ['favicon_media_asset_id' => $otherAsset->getKey()]))
            ->assertNotFound();
    }

    public function test_unsafe_social_urls_and_invalid_contact_and_branding_values_are_rejected(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->settings($tenant, $store);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->patch(route('admin.stores.settings.update', $store), $this->payload($store, [
            'contact_email' => 'not-an-email', 'contact_phone' => '123', 'primary_color' => 'red',
            'social_links' => ['facebook' => 'javascript:alert(1)', 'instagram' => 'https://evil.example/profile'],
        ]))->assertInvalid(['contact_email', 'contact_phone', 'primary_color', 'social_links.facebook', 'social_links.instagram']);
    }

    public function test_stale_updates_return_conflict_instead_of_overwriting_changes(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->settings($tenant, $store);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);
        $stale = $this->payload($store);

        $this->travel(1)->seconds();
        $this->patch(route('admin.stores.settings.update', $store), $this->payload($store, ['display_name' => 'First']))->assertRedirect();
        $this->patchJson(route('admin.stores.settings.update', $store), [...$stale, 'display_name' => 'Stale'])->assertConflict();
        $this->assertDatabaseMissing('store_settings', ['store_id' => $store->getKey(), 'display_name' => 'Stale']);
    }

    private function settings(Tenant $tenant, Store $store): void
    {
        app(GetStoreSettings::class)->handle($tenant, $store->getKey());
    }

    /** @param array<string, mixed> $overrides */
    private function payload(Store $store, array $overrides = []): array
    {
        $store->refresh();
        $settings = $store->settings()->firstOrFail();

        return array_replace_recursive([
            'display_name' => null, 'short_description' => null, 'contact_email' => null,
            'contact_phone' => null, 'whatsapp_phone' => null, 'address_line_1' => null,
            'address_line_2' => null, 'district' => null, 'province' => null, 'department' => null,
            'country_code' => 'PE', 'postal_code' => null,
            'social_links' => ['facebook' => null, 'instagram' => null, 'tiktok' => null, 'youtube' => null, 'whatsapp' => null],
            'currency' => $store->currency, 'timezone' => $store->timezone, 'locale' => $store->locale,
            'logo_media_asset_id' => null, 'favicon_media_asset_id' => null,
            'primary_color' => '#17211B', 'secondary_color' => null,
            'store_updated_at' => $store->updated_at->toISOString(),
            'settings_updated_at' => $settings->updated_at->toISOString(),
        ], $overrides);
    }

    private function asset(Tenant $tenant, User $user): MediaAsset
    {
        $id = (string) Str::uuid7();
        $key = "media/tenants/{$tenant->getKey()}/{$id}/master.jpg";
        Storage::disk('store-settings-media-test')->put($key, 'image');

        return MediaAsset::create([
            'id' => $id, 'tenant_id' => $tenant->getKey(), 'type' => MediaType::Image,
            'disk' => 'store-settings-media-test', 'storage_key' => $key, 'original_filename' => 'brand.jpg',
            'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size_bytes' => 5, 'width' => 100,
            'height' => 100, 'checksum' => hash('sha256', 'image'), 'status' => MediaStatus::Ready,
            'created_by_user_id' => $user->getKey(),
        ]);
    }
}
