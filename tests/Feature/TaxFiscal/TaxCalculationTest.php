<?php

namespace Tests\Feature\TaxFiscal;

use App\Models\User;
use App\Modules\Tax\Application\ResolveEffectiveTaxRate;
use App\Modules\Tax\Application\TaxCalculator;
use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use App\Modules\Tax\Domain\Enums\TaxCategoryStatus;
use App\Modules\Tax\Domain\Models\TaxCategory;
use App\Modules\Tax\Domain\Models\TaxRate;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class TaxCalculationTest extends TaxFiscalFeatureTestCase
{
    public function test_inclusive_standard_tax_is_decomposed_with_decimal_math(): void
    {
        $category = new TaxCategory(['code' => 'STANDARD', 'status' => 'ACTIVE']);
        $rate = new TaxRate(['rate' => '18.000000', 'status' => 'ACTIVE']);

        $calculation = app(TaxCalculator::class)->calculateInclusive('118.0000', $category, $rate);

        $this->assertSame('100.0000', $calculation->baseAmount);
        $this->assertSame('18.0000', $calculation->taxAmount);
        $this->assertSame('118.0000', $calculation->total);
        $this->assertSame('18.000000', $calculation->rate);
        $this->assertSame(TaxCategoryCode::Standard, $calculation->categoryCode);

        $fraction = app(TaxCalculator::class)->calculateInclusive('0.3000', $category, $rate);
        $this->assertSame('0.2542', $fraction->baseAmount);
        $this->assertSame('0.0458', $fraction->taxAmount);
        $this->assertSame('0.3000', $fraction->total);
    }

    public function test_exempt_and_unaffected_amounts_remain_whole_at_zero_rate(): void
    {
        foreach ([TaxCategoryCode::Exempt, TaxCategoryCode::Unaffected] as $code) {
            $category = new TaxCategory(['code' => $code->value, 'status' => 'ACTIVE']);
            $rate = new TaxRate(['rate' => '0.000000', 'status' => 'ACTIVE']);
            $calculation = app(TaxCalculator::class)->calculateInclusive('19.9901', $category, $rate);

            $this->assertSame('19.9901', $calculation->baseAmount);
            $this->assertSame('0.0000', $calculation->taxAmount);
            $this->assertSame('19.9901', $calculation->total);
            $this->assertSame($code, $calculation->categoryCode);
        }
    }

    public function test_non_standard_category_rejects_nonzero_rate(): void
    {
        $this->expectException(ValidationException::class);

        app(TaxCalculator::class)->calculateInclusive(
            '10.0000',
            new TaxCategory(['code' => 'EXEMPT', 'status' => 'ACTIVE']),
            new TaxRate(['rate' => '0.000001', 'status' => 'ACTIVE']),
        );
    }

    public function test_effective_rate_respects_inclusive_boundaries_and_ignores_inactive_rates(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $category = $this->createTaxCategory($tenant);
        $expected = $this->createTaxRate($tenant, $category, attributes: [
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ]);
        $this->createTaxRate($tenant, $category, '20.000000', [
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'status' => 'INACTIVE',
        ]);

        foreach (['2026-01-01', '2026-12-31'] as $date) {
            $actual = app(ResolveEffectiveTaxRate::class)->handle($category, CarbonImmutable::parse($date));
            $this->assertTrue($actual->is($expected));
        }
    }

    public function test_effective_rate_rejects_gaps_overlaps_and_inactive_category(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $category = $this->createTaxCategory($tenant);
        $this->createTaxRate($tenant, $category, attributes: ['valid_from' => '2026-02-01']);

        try {
            app(ResolveEffectiveTaxRate::class)->handle($category, CarbonImmutable::parse('2026-01-31'));
            $this->fail('A rate gap must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tax_rate', $exception->errors());
        }

        TaxRate::query()->create([
            'tenant_id' => $tenant->getKey(), 'tax_category_id' => $category->getKey(),
            'name' => 'Deliberately overlapping fixture', 'rate' => '19.000000',
            'valid_from' => '2026-02-01', 'valid_until' => null, 'status' => 'ACTIVE',
        ]);
        try {
            app(ResolveEffectiveTaxRate::class)->handle($category, CarbonImmutable::parse('2026-02-01'));
            $this->fail('Ambiguous rates must fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('More than one', $exception->errors()['tax_rate'][0]);
        }

        $category->update(['status' => TaxCategoryStatus::Inactive]);
        $this->expectException(ValidationException::class);
        app(ResolveEffectiveTaxRate::class)->handle($category->fresh(), CarbonImmutable::parse('2026-03-01'));
    }
}
