<?php

namespace Tests\Feature\TaxFiscal;

use App\Models\User;
use App\Modules\Fiscal\Application\GenerateFiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class FiscalDocumentGenerationTest extends TaxFiscalFeatureTestCase
{
    public function test_receipt_generation_snapshots_issuer_customer_item_tax_and_totals(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order, , , $issuer, $series] = $this->readyFiscalOrder($tenant, $store, $actor);

        $document = $this->generateReceipt($tenant, $store, $order, $actor);

        $this->assertSame('B001-00000001', $document->full_number);
        $this->assertSame('Fixture Commerce SAC', $document->issuer_legal_name);
        $this->assertSame('Checkout Buyer', $document->customer_name);
        $this->assertSame('84.7458', $document->taxable_amount);
        $this->assertSame('15.2542', $document->tax_amount);
        $this->assertSame('100.0000', $document->subtotal);
        $this->assertSame('100.0000', $document->grand_total);
        $item = $document->items->sole();
        $this->assertSame('STANDARD', $item->tax_category_code->value);
        $this->assertSame('18.000000', $item->tax_rate);
        $this->assertSame('100.0000', $item->line_total);
        $this->assertSame(2, $series->fresh()->next_number);

        $issuer->update(['legal_name' => 'Changed after issue']);
        $order->items()->sole()->product()->update(['name' => 'Changed product']);
        $this->assertSame('Fixture Commerce SAC', $document->fresh()->issuer_legal_name);
        $this->assertNotSame('Changed product', $document->fresh()->items()->sole()->description);
    }

    public function test_invoice_requires_ruc_name_and_address_and_failure_has_no_side_effects(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order, , , , $series] = $this->readyFiscalOrder($tenant, $store, $actor, 'INVOICE');

        try {
            app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, [
                'document_type' => 'INVOICE',
            ], $actor);
            $this->fail('An invoice without RUC details must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_document_type', $exception->errors());
        }

        $this->assertSame(0, FiscalDocument::query()->count());
        $this->assertSame(1, $series->fresh()->next_number);

        $document = app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, [
            'document_type' => 'INVOICE', 'customer_document_type' => 'RUC',
            'customer_document_number' => '20555555555', 'customer_name' => 'Buyer SAC',
            'customer_address' => 'Av. Buyer 456',
        ], $actor);
        $this->assertSame('20555555555', $document->customer_document_number);
        $this->assertSame('Buyer SAC', $document->customer_name);
    }

    public function test_retry_is_idempotent_but_different_primary_snapshot_is_rejected(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order, , , , $series] = $this->readyFiscalOrder($tenant, $store, $actor);

        $first = $this->generateReceipt($tenant, $store, $order, $actor);
        $retry = $this->generateReceipt($tenant, $store, $order, $actor);
        $this->assertTrue($first->is($retry));
        $this->assertSame(1, FiscalDocument::query()->count());
        $this->assertSame(2, $series->fresh()->next_number);

        try {
            $this->generateReceipt($tenant, $store, $order, $actor, ['customer_name' => 'Different buyer']);
            $this->fail('A changed idempotency snapshot must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }
        $this->assertSame(1, FiscalDocument::query()->count());
        $this->assertSame(2, $series->fresh()->next_number);
    }

    public function test_sequential_orders_increment_one_locked_series_without_duplicate_numbers(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$firstOrder, , , , $series] = $this->readyFiscalOrder($tenant, $store, $actor);
        [$secondOrder, , , $variant] = $this->readyOrder($tenant, $store, $actor, 1);
        $variant->product()->update(['tax_category_id' => $tenant->taxCategories()->sole()->getKey()]);

        $first = $this->generateReceipt($tenant, $store, $firstOrder, $actor);
        $second = $this->generateReceipt($tenant, $store, $secondOrder, $actor);

        $this->assertSame(['B001-00000001', 'B001-00000002'], [$first->full_number, $second->full_number]);
        $this->assertSame([1, 2], FiscalDocument::query()->orderBy('sequence_number')->pluck('sequence_number')->all());
        $this->assertSame(3, $series->fresh()->next_number);
    }

    public function test_generation_rejects_wrong_store_and_tenant_without_side_effects(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order, , , , $series] = $this->readyFiscalOrder($tenant, $store, $actor);
        $otherStore = $this->createStore($tenant);
        $foreignTenant = $this->createTenantFor(User::factory()->create());

        foreach ([[$tenant, $otherStore], [$foreignTenant, $store]] as [$attemptTenant, $attemptStore]) {
            try {
                $this->generateReceipt($attemptTenant, $attemptStore, $order, $actor);
                $this->fail('Cross-boundary generation must fail.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, FiscalDocument::query()->count());
        $this->assertSame(1, $series->fresh()->next_number);
    }

    public function test_legacy_order_without_tax_snapshot_is_rejected_without_side_effects(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyOrder($tenant, $store, $actor, 1);
        $this->createIssuer($tenant);
        $series = $this->createFiscalSeries($store);
        $order->update([
            'tax_snapshot_version' => null, 'taxable_amount' => null, 'exempt_amount' => null,
            'unaffected_amount' => null, 'tax_calculated_at' => null,
        ]);
        try {
            $this->generateReceipt($tenant, $store, $order, $actor);
            $this->fail('A legacy order without a tax snapshot must fail.');
        } catch (ValidationException $exception) {
            $this->assertSame('ORDER_TAX_SNAPSHOT_MISSING', $exception->errors()['order'][0]);
        }

        $this->assertSame(0, FiscalDocument::query()->count());
        $this->assertSame(1, $series->fresh()->next_number);
    }
}
