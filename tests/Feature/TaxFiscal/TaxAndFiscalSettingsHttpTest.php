<?php

namespace Tests\Feature\TaxFiscal;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use Inertia\Testing\AssertableInertia;

class TaxAndFiscalSettingsHttpTest extends TaxFiscalFeatureTestCase
{
    public function test_owner_can_create_update_and_view_tenant_tax_configuration(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $this->useContext($user, $tenant, $store);

        $this->post(route('admin.tax-categories.store'), [
            'code' => ' standard ', 'name' => ' General ', 'status' => ' active ',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $category = $tenant->taxCategories()->sole();
        $this->post(route('admin.tax-rates.store'), [
            'tax_category_id' => $category->getKey(), 'name' => ' IGV ', 'rate' => '18.000000',
            'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31', 'status' => 'active',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $rate = $tenant->taxRates()->sole();
        $this->patch(route('admin.tax-categories.update', $category), [
            'code' => 'STANDARD', 'name' => 'IGV General', 'status' => 'ACTIVE',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('admin.tax-rates.update', $rate), [
            'tax_category_id' => $category->getKey(), 'name' => 'IGV 18', 'rate' => '18',
            'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31', 'status' => 'ACTIVE',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get(route('admin.taxes.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/taxes/Index')
            ->has('categories', 1)->where('categories.0.name', 'IGV General')
            ->has('rates', 1)->where('rates.0.name', 'IGV 18'));
    }

    public function test_tax_crud_rejects_cross_tenant_references_and_resource_updates(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $store = $this->createStore($tenant);
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $foreignCategory = $this->createTaxCategory($foreignTenant);
        $foreignRate = $this->createTaxRate($foreignTenant, $foreignCategory);
        $this->useContext($user, $tenant, $store);

        $this->post(route('admin.tax-rates.store'), [
            'tax_category_id' => $foreignCategory->getKey(), 'name' => 'Foreign', 'rate' => '18',
            'valid_from' => null, 'valid_until' => null, 'status' => 'ACTIVE',
        ])->assertSessionHasErrors('tax_category_id');
        $this->patch(route('admin.tax-categories.update', $foreignCategory), [
            'code' => 'STANDARD', 'name' => 'Leaked', 'status' => 'ACTIVE',
        ])->assertNotFound();
        $this->patch(route('admin.tax-rates.update', $foreignRate), [
            'tax_category_id' => $foreignCategory->getKey(), 'name' => 'Leaked', 'rate' => '18',
            'valid_from' => null, 'valid_until' => null, 'status' => 'ACTIVE',
        ])->assertRedirect()->assertSessionHasErrors('tax_category_id');
        $this->assertSame('18.000000', $foreignRate->fresh()->rate);
        $this->get(route('admin.taxes.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('categories', 0)->has('rates', 0));
    }

    public function test_member_cannot_manage_or_view_tax_and_fiscal_settings(): void
    {
        $member = User::factory()->create();
        $tenant = $this->createTenantFor($member, SystemRole::Member);
        $store = $this->createStore($tenant);
        $this->useContext($member, $tenant, $store);

        $this->get(route('admin.taxes.index'))->assertForbidden();
        $this->post(route('admin.tax-categories.store'), [])->assertForbidden();
        $this->get(route('admin.fiscal.settings.show'))->assertForbidden();
        $this->patch(route('admin.fiscal.settings.update'), [])->assertForbidden();
        $this->post(route('admin.fiscal-series.store'), [])->assertForbidden();
    }

    public function test_issuer_and_series_are_managed_in_active_tenant_and_store_only(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $storeA = $this->createStore($tenant);
        $storeB = $this->createStore($tenant);
        $this->useContext($user, $tenant, $storeA);

        $this->patch(route('admin.fiscal.settings.update'), $this->issuerPayload())->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.fiscal-series.store'), $this->seriesPayload())->assertRedirect()->assertSessionHasNoErrors();
        $seriesA = $storeA->fiscalSeries()->sole();
        $this->assertSame($tenant->getKey(), $tenant->fiscalIssuerProfile()->sole()->tenant_id);

        $this->useContext($user, $tenant, $storeB);
        $this->get(route('admin.fiscal.settings.show'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('issuerProfile.tax_id', '20123456789')->has('series', 0));
        $this->patch(route('admin.fiscal-series.update', $seriesA), $this->seriesPayload('B009'))->assertNotFound();
        $this->post(route('admin.fiscal-series.store'), $this->seriesPayload('B002'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fiscal_series', ['store_id' => $storeB->getKey(), 'series' => 'B002']);
    }

    public function test_product_tax_assignment_requires_manage_permission_and_current_tenant_category(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $store = $this->createStore($tenant);
        $product = $this->createProduct($tenant);
        $category = $this->createTaxCategory($tenant);
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $foreignCategory = $this->createTaxCategory($foreignTenant);
        $this->useContext($owner, $tenant, $store);

        $payload = [
            'name' => $product->name, 'description' => null, 'status' => ProductStatus::Active->value,
            'tax_category_id' => $category->getKey(), 'categories' => [],
        ];
        $this->put(route('admin.products.update', $product), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->taxCategory->is($category));
        $this->put(route('admin.products.update', $product), [
            ...$payload, 'tax_category_id' => $foreignCategory->getKey(),
        ])->assertSessionHasErrors('tax_category_id');

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $memberStore = $this->createStore($memberTenant);
        $memberProduct = $this->createProduct($memberTenant);
        $this->useContext($member, $memberTenant, $memberStore);
        $this->put(route('admin.products.update', $memberProduct), [
            'name' => $memberProduct->name, 'description' => null, 'status' => ProductStatus::Active->value,
            'tax_category_id' => null, 'categories' => [],
        ])->assertForbidden();
    }

    private function issuerPayload(): array
    {
        return [
            'country_code' => 'pe', 'tax_id' => '20123456789', 'legal_name' => 'Fixture Commerce SAC',
            'trade_name' => '', 'fiscal_address_line_1' => 'Av. Test 123', 'fiscal_address_line_2' => '',
            'district' => 'Lima', 'province' => 'Lima', 'region' => 'Lima', 'postal_code' => '',
            'ubigeo' => '150101', 'status' => 'active',
        ];
    }

    private function seriesPayload(string $series = 'B001'): array
    {
        return [
            'document_type' => 'receipt', 'series' => $series, 'number_padding' => 8,
            'status' => 'active',
        ];
    }
}
