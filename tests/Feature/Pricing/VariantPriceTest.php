<?php

namespace Tests\Feature\Pricing;

use App\Models\User;
use App\Modules\Pricing\Application\ArchivePriceList;
use App\Modules\Pricing\Application\PriceResolver;
use App\Modules\Pricing\Application\SetVariantPrice;
use App\Modules\Pricing\Domain\Models\VariantPrice;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class VariantPriceTest extends PricingFeatureTestCase
{
    public function test_price_is_created_updated_and_kept_as_exact_decimal_string(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $list = $tenant->defaultPriceList()->sole();
        $variant = $this->createVariant($tenant);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->put(route('admin.pricing.price-lists.variants.update', [$list, $variant]), ['amount' => '10.50'])
            ->assertRedirect();
        $price = VariantPrice::query()->sole();
        $this->assertIsString($price->amount);
        $this->assertSame('10.5000', $price->amount);

        $this->put(route('admin.pricing.price-lists.variants.update', [$list, $variant]), ['amount' => '1000.9999'])
            ->assertRedirect();
        $this->assertSame('1000.9999', $price->fresh()->amount);
        $this->assertSame(1, VariantPrice::query()->count());
    }

    public function test_zero_is_allowed_and_invalid_amounts_are_rejected(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $list = $tenant->defaultPriceList()->sole();
        $variant = $this->createVariant($tenant);
        $route = route('admin.pricing.price-lists.variants.update', [$list, $variant]);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()]);

        $this->put($route, ['amount' => '0'])->assertRedirect();
        $this->assertSame('0.0000', VariantPrice::query()->sole()->amount);
        foreach (['-1', '1.00001', 'NaN', 'Infinity', '1e3', 'abc'] as $invalid) {
            $this->put($route, ['amount' => $invalid])->assertSessionHasErrors('amount');
        }
    }

    public function test_variant_can_be_unpriced_and_have_different_prices_per_list(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $default = $tenant->defaultPriceList()->sole();
        $other = $this->createPriceList($tenant);
        $this->assertFalse($variant->prices()->exists());

        app(SetVariantPrice::class)->handle($tenant, $default, $variant, '99.90');
        app(SetVariantPrice::class)->handle($tenant, $other, $variant, '29.90');
        $this->assertEqualsCanonicalizing(['99.9000', '29.9000'], $variant->prices()->pluck('amount')->all());
    }

    public function test_unique_constraint_allows_one_price_per_list_and_variant(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $list = $tenant->defaultPriceList()->sole();
        app(SetVariantPrice::class)->handle($tenant, $list, $variant, '10');

        $this->expectException(QueryException::class);
        VariantPrice::create([
            'tenant_id' => $tenant->getKey(),
            'price_list_id' => $list->getKey(),
            'product_variant_id' => $variant->getKey(),
            'amount' => '20.0000',
        ]);
    }

    public function test_price_resolver_uses_default_or_explicit_list(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $variant = $this->createVariant($tenant);
        $default = $tenant->defaultPriceList()->sole();
        $other = $this->createPriceList($tenant);
        app(SetVariantPrice::class)->handle($tenant, $default, $variant, '10');
        app(SetVariantPrice::class)->handle($tenant, $other, $variant, '20');
        $resolver = app(PriceResolver::class);

        $this->assertSame('10.0000', $resolver->priceFor($tenant, $variant)?->amount);
        $this->assertSame('20.0000', $resolver->priceFor($tenant, $variant, $other)?->amount);
    }

    public function test_cross_tenant_list_and_variant_combinations_return_not_found(): void
    {
        $user = User::factory()->create();
        $tenantA = $this->createTenantFor($user);
        $tenantB = $this->createTenantFor(User::factory()->create());
        $listA = $tenantA->defaultPriceList()->sole();
        $listB = $tenantB->defaultPriceList()->sole();
        $variantA = $this->createVariant($tenantA);
        $variantB = $this->createVariant($tenantB);
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenantA->getKey()]);

        $this->put(route('admin.pricing.price-lists.variants.update', [$listA, $variantB]), ['amount' => '10'])
            ->assertNotFound();
        $this->put(route('admin.pricing.price-lists.variants.update', [$listB, $variantA]), ['amount' => '10'])
            ->assertNotFound();
    }

    public function test_database_rejects_cross_tenant_variant_price(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $listA = $tenantA->defaultPriceList()->sole();
        $variantB = $this->createVariant($tenantB);

        $this->expectException(QueryException::class);
        DB::table('variant_prices')->insert([
            'id' => fake()->uuid(),
            'tenant_id' => $tenantA->getKey(),
            'price_list_id' => $listA->getKey(),
            'product_variant_id' => $variantB->getKey(),
            'amount' => '10.0000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_price_can_be_removed_but_archived_list_prices_are_preserved(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $variant = $this->createVariant($tenant);
        $list = $this->createPriceList($tenant);
        app(SetVariantPrice::class)->handle($tenant, $list, $variant, '10');
        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->delete(route('admin.pricing.price-lists.variants.destroy', [$list, $variant]))->assertRedirect();
        $this->assertFalse(VariantPrice::query()->exists());

        app(SetVariantPrice::class)->handle($tenant, $list, $variant, '20');
        app(ArchivePriceList::class)->handle($tenant, $list);
        $this->put(route('admin.pricing.price-lists.variants.update', [$list, $variant]), ['amount' => '30'])
            ->assertSessionHasErrors('amount');
        $this->delete(route('admin.pricing.price-lists.variants.destroy', [$list, $variant]))
            ->assertSessionHasErrors('amount');
        $this->assertSame('20.0000', VariantPrice::query()->sole()->amount);
    }

    public function test_product_edit_exposes_default_price_only_with_pricing_permission(): void
    {
        $user = User::factory()->create();
        $tenant = $this->createTenantFor($user);
        $variant = $this->createVariant($tenant, 'EDIT-SKU');
        app(SetVariantPrice::class)->handle($tenant, $tenant->defaultPriceList()->sole(), $variant, '49.90');

        $this->actingAs($user)->withSession([TenantContext::SESSION_KEY => $tenant->getKey()])
            ->get(route('admin.products.edit', $variant->product))
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.variants.0.default_price', '49.9000')
                ->where('pricing.currency', 'PEN')
                ->where('pricing.can_update', true));
    }

    public function test_resolver_rejects_foreign_variant_and_list(): void
    {
        $tenantA = $this->createTenantFor(User::factory()->create());
        $tenantB = $this->createTenantFor(User::factory()->create());
        $resolver = app(PriceResolver::class);

        $this->expectException(ModelNotFoundException::class);
        $resolver->priceFor($tenantA, $this->createVariant($tenantB), $tenantB->defaultPriceList()->sole());
    }
}
