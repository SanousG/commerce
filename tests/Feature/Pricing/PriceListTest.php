<?php

namespace Tests\Feature\Pricing;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

class PriceListTest extends PricingFeatureTestCase
{
    public function test_owner_creates_non_default_tenant_price_list_and_ignores_tenant_id(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $other = $this->createTenantFor(User::factory()->create());

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.pricing.price-lists.store'), [
                ...$this->priceListPayload(), 'tenant_id' => $other->getKey(),
            ])->assertRedirect();

        $created = $tenant->priceLists()->where('code', 'WHOLESALE')->sole();
        $this->assertFalse($created->is_default);
        $this->assertSame(PriceListStatus::Active, $created->status);
        $this->assertSame(1, $tenant->priceLists()->where('is_default', true)->count());
        $this->assertFalse($other->priceLists()->where('code', 'WHOLESALE')->exists());
    }

    public function test_admin_creates_and_member_only_views_price_lists(): void
    {
        $admin = User::factory()->create();
        $adminTenant = $this->createTenantFor($admin, SystemRole::Admin);
        $this->actingAs($admin)->withSession([TenantContext::SESSION_KEY => $adminTenant->getKey()])
            ->post(route('admin.pricing.price-lists.store'), $this->priceListPayload())->assertRedirect();

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $this->actingAs($member)->withSession([TenantContext::SESSION_KEY => $memberTenant->getKey()])
            ->get(route('admin.pricing.index'))->assertOk();
        $this->get(route('admin.pricing.price-lists.create'))->assertForbidden();
        $this->post(route('admin.pricing.price-lists.store'), $this->priceListPayload())->assertForbidden();

        $default = $memberTenant->defaultPriceList()->sole();
        $variant = $this->createVariant($memberTenant);
        $this->put(route('admin.pricing.price-lists.variants.update', [$default, $variant]), ['amount' => '10'])
            ->assertForbidden();
        $this->patch(route('admin.pricing.price-lists.archive', $default))->assertForbidden();
    }

    public function test_code_is_unique_case_insensitively_per_tenant_but_reusable_between_tenants(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $this->createPriceList($tenantA, ['code' => 'WHOLESALE']);
        $this->createPriceList($tenantB, ['code' => 'WHOLESALE']);

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()])
            ->post(route('admin.pricing.price-lists.store'), $this->priceListPayload('wholesale'))
            ->assertSessionHasErrors('code');
    }

    public function test_currency_must_be_supported(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.pricing.price-lists.store'), [
                ...$this->priceListPayload(), 'currency' => 'ZZZ',
            ])->assertSessionHasErrors('currency');
    }

    public function test_make_default_is_atomic_and_requires_active_list(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $default = $tenant->defaultPriceList()->sole();
        $replacement = $this->createPriceList($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->post(route('admin.pricing.price-lists.make-default', $replacement))->assertRedirect();

        $this->assertFalse($default->fresh()->is_default);
        $this->assertTrue($replacement->fresh()->is_default);
        $this->assertSame(1, $tenant->priceLists()->where('is_default', true)->count());

        $inactive = $this->createPriceList($tenant, ['code' => 'INACTIVE']);
        $inactive->update(['status' => PriceListStatus::Inactive]);
        $this->post(route('admin.pricing.price-lists.make-default', $inactive))
            ->assertSessionHasErrors('price_list');
    }

    public function test_default_cannot_be_archived_or_made_inactive(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $default = $tenant->defaultPriceList()->sole();
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->patch(route('admin.pricing.price-lists.archive', $default))->assertSessionHasErrors('price_list');
        $this->put(route('admin.pricing.price-lists.update', $default), [
            'name' => $default->name, 'currency' => $default->currency, 'status' => 'INACTIVE',
        ])->assertSessionHasErrors('status');
    }

    public function test_database_rejects_removing_the_only_default(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());

        $this->expectException(QueryException::class);
        $tenant->defaultPriceList()->sole()->update(['is_default' => false]);
    }

    public function test_update_permission_cannot_archive_or_reactivate_a_price_list(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $list = $this->createPriceList($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->put(route('admin.pricing.price-lists.update', $list), [
            'name' => $list->name, 'currency' => $list->currency, 'status' => 'ARCHIVED',
        ])->assertSessionHasErrors('status');

        $list->update(['status' => PriceListStatus::Archived]);
        $this->put(route('admin.pricing.price-lists.update', $list), [
            'name' => $list->name, 'currency' => $list->currency, 'status' => 'ACTIVE',
        ])->assertSessionHasErrors('status');
    }

    public function test_currency_cannot_change_after_price_and_name_change_keeps_code(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $list = $this->createPriceList($tenant, ['code' => 'STABLE']);
        app(SetVariantPrice::class)->handle($tenant, $list, $this->createVariant($tenant), '10.50');
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->put(route('admin.pricing.price-lists.update', $list), [
                'name' => 'Renamed', 'currency' => 'EUR', 'status' => 'ACTIVE',
            ])->assertSessionHasErrors('currency');

        $this->put(route('admin.pricing.price-lists.update', $list), [
            'name' => 'Renamed', 'currency' => 'USD', 'status' => 'ACTIVE',
        ])->assertRedirect();
        $this->assertSame('STABLE', $list->fresh()->code);
        $this->assertSame('Renamed', $list->fresh()->name);
    }

    public function test_cross_tenant_price_lists_are_hidden_from_list_edit_and_actions(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $foreign = $this->createPriceList($this->createTenantFor(User::factory()->create()));
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->get(route('admin.pricing.index'))->assertInertia(fn (Assert $page) => $page
            ->has('priceLists.data', 1)
            ->where('priceLists.data.0.code', 'DEFAULT'));
        $this->get(route('admin.pricing.price-lists.edit', $foreign))->assertNotFound();
        $this->post(route('admin.pricing.price-lists.make-default', $foreign))->assertNotFound();
        $this->patch(route('admin.pricing.price-lists.archive', $foreign))->assertNotFound();
    }

    public function test_switching_tenant_changes_visible_pricing(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor($user);
        $this->createPriceList($tenantA, ['name' => 'Tenant A', 'code' => 'A']);
        $this->createPriceList($tenantB, ['name' => 'Tenant B', 'code' => 'B']);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);

        $this->get(route('admin.pricing.index'))->assertInertia(fn (Assert $page) => $page
            ->where('priceLists.data.1.code', 'A'));
        $this->post(route('admin.tenants.select', $tenantB));
        $this->get(route('admin.pricing.index'))->assertInertia(fn (Assert $page) => $page
            ->where('priceLists.data.1.code', 'B'));
    }
}
