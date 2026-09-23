<?php

namespace Tests\Feature\Pricing;

use App\Models\User;
use App\Modules\Pricing\Domain\Enums\PriceListStatus;
use App\Modules\Tenancy\Application\CreateTenant;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantPricingTest extends PricingFeatureTestCase
{
    public function test_new_tenant_gets_configured_default_price_list(): void
    {
        config()->set('commerce.default_currency', 'USD');
        $tenant = app(CreateTenant::class)->handle(User::factory()->create(), 'New tenant');
        $default = $tenant->defaultPriceList()->sole();

        $this->assertSame('Precio principal', $default->name);
        $this->assertSame('DEFAULT', $default->code);
        $this->assertSame('USD', $default->currency);
        $this->assertSame(PriceListStatus::Active, $default->status);
        $this->assertTrue($default->is_default);
    }

    public function test_pricing_bootstrap_failure_rolls_back_tenant_and_membership(): void
    {
        config()->set('commerce.default_currency', 'ZZZ');

        try {
            app(CreateTenant::class)->handle(User::factory()->create(), 'Must Roll Back Pricing');
            $this->fail('Invalid pricing configuration should fail.');
        } catch (RuntimeException) {
            $this->assertDatabaseMissing('tenants', ['name' => 'Must Roll Back Pricing']);
            $this->assertDatabaseCount('tenant_members', 0);
            $this->assertDatabaseCount('price_lists', 0);
        }
    }

    public function test_database_rejects_tenant_without_default_price_list(): void
    {
        Tenant::create(['name' => 'Incomplete', 'slug' => 'incomplete', 'status' => TenantStatus::Active]);

        $this->expectException(QueryException::class);
        DB::statement('SET CONSTRAINTS tenants_default_price_list_check IMMEDIATE');
    }

    public function test_lowercase_configured_currency_is_normalized(): void
    {
        config()->set('commerce.default_currency', ' usd ');
        $tenant = app(CreateTenant::class)->handle(User::factory()->create(), 'Normalized currency');

        $this->assertSame('USD', $tenant->defaultPriceList()->sole()->currency);
    }
}
