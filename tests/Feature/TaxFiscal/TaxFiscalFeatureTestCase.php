<?php

namespace Tests\Feature\TaxFiscal;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Fiscal\Application\GenerateFiscalDocument;
use App\Modules\Fiscal\Application\SaveFiscalSeries;
use App\Modules\Fiscal\Application\UpdateFiscalIssuerProfile;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Domain\Models\FiscalSeries;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tax\Application\SaveTaxCategory;
use App\Modules\Tax\Application\SaveTaxRate;
use App\Modules\Tax\Domain\Models\TaxCategory;
use App\Modules\Tax\Domain\Models\TaxRate;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Tests\Feature\Orders\OrdersFeatureTestCase;

abstract class TaxFiscalFeatureTestCase extends OrdersFeatureTestCase
{
    protected function createTaxCategory(Tenant $tenant, string $code = 'STANDARD', array $attributes = []): TaxCategory
    {
        return app(SaveTaxCategory::class)->create($tenant, array_replace([
            'code' => $code,
            'name' => ucfirst(strtolower($code)),
            'status' => 'ACTIVE',
        ], $attributes));
    }

    protected function createTaxRate(Tenant $tenant, TaxCategory $category, string $rate = '18.000000', array $attributes = []): TaxRate
    {
        return app(SaveTaxRate::class)->create($tenant, array_replace([
            'tax_category_id' => $category->getKey(),
            'name' => $rate.' percent',
            'rate' => $rate,
            'valid_from' => null,
            'valid_until' => null,
            'status' => 'ACTIVE',
        ], $attributes));
    }

    protected function createIssuer(Tenant $tenant, array $attributes = []): FiscalIssuerProfile
    {
        return app(UpdateFiscalIssuerProfile::class)->handle($tenant, array_replace([
            'country_code' => 'PE',
            'tax_id' => '20123456789',
            'legal_name' => 'Fixture Commerce SAC',
            'trade_name' => 'Fixture Commerce',
            'fiscal_address_line_1' => 'Av. Test 123',
            'fiscal_address_line_2' => null,
            'district' => 'Lima',
            'province' => 'Lima',
            'region' => 'Lima',
            'postal_code' => '15001',
            'ubigeo' => '150101',
            'status' => 'ACTIVE',
        ], $attributes));
    }

    protected function createFiscalSeries(Store $store, string $type = 'RECEIPT', array $attributes = []): FiscalSeries
    {
        $tenant = $store->tenant()->firstOrFail();

        return app(SaveFiscalSeries::class)->create($tenant, $store, array_replace([
            'document_type' => $type,
            'series' => match ($type) {
                'INVOICE' => 'F001',
                'CREDIT_NOTE' => 'FC01',
                default => 'B001',
            },
            'number_padding' => 8,
            'status' => 'ACTIVE',
        ], $attributes));
    }

    protected function configureFiscalProduct(Tenant $tenant, Store $store, Product $product, string $type = 'RECEIPT'): array
    {
        $category = $tenant->taxCategories()->where('code', 'STANDARD')->first()
            ?? $this->createTaxCategory($tenant);
        $rate = $category->rates()->first() ?? $this->createTaxRate($tenant, $category);
        $product->update(['tax_category_id' => $category->getKey()]);
        $issuer = $this->createIssuer($tenant);
        $series = $this->createFiscalSeries($store, $type);

        return [$category, $rate, $issuer, $series];
    }

    protected function readyFiscalOrder(Tenant $tenant, Store $store, User $actor, string $type = 'RECEIPT'): array
    {
        [$order, , , $variant] = $this->readyOrder($tenant, $store, $actor, 1);
        [$category, $rate, $issuer, $series] = $this->configureFiscalProduct(
            $tenant,
            $store,
            $variant->product()->firstOrFail(),
            $type,
        );

        return [$order, $category, $rate, $issuer, $series];
    }

    protected function generateReceipt(Tenant $tenant, Store $store, Order $order, User $actor, array $input = []): FiscalDocument
    {
        return app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, array_replace([
            'document_type' => 'RECEIPT',
        ], $input), $actor);
    }
}
