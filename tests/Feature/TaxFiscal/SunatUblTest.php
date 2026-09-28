<?php

namespace Tests\Feature\TaxFiscal;

use App\Models\User;
use App\Modules\Fiscal\Application\GenerateFiscalDocument;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatDocumentFileName;
use App\Modules\Fiscal\Infrastructure\Sunat\Ubl21FiscalDocumentSerializer;
use App\Modules\Fiscal\Infrastructure\Sunat\UblSchemaValidator;
use DOMDocument;
use DOMXPath;

final class SunatUblTest extends TaxFiscalFeatureTestCase
{
    public function test_invoice_ubl_uses_snapshot_and_validates_against_provisioned_official_xsd(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor, 'INVOICE');
        $document = app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, [
            'document_type' => 'INVOICE',
            'customer_document_type' => 'RUC',
            'customer_document_number' => '20555555555',
            'customer_name' => 'Comprador & Compañía SAC',
            'customer_address' => 'Av. Prueba 456',
        ], $actor);

        $payload = app(Ubl21FiscalDocumentSerializer::class)->buildPayload($document);
        $validation = app(UblSchemaValidator::class)->validateInvoice($payload->contents);

        $this->assertTrue($validation->valid, json_encode($validation->errors, JSON_PRETTY_PRINT));
        $this->assertSame('20123456789-01-F001-1.xml', $payload->fileName);
        $this->assertSame(hash('sha256', $payload->contents), $payload->sha256);
        $this->assertStringNotContainsString('<!--', $payload->contents);
        $this->assertStringContainsString('Comprador &amp; Compañía SAC', $payload->contents);
        $this->assertXmlValues($payload->contents, '01', 'F001-00000001', '20555555555');
    }

    public function test_receipt_has_its_own_document_code_and_anonymous_identity(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor, 'RECEIPT');
        $document = $this->generateReceipt($tenant, $store, $order, $actor);

        $payload = app(Ubl21FiscalDocumentSerializer::class)->buildPayload($document);
        $validation = app(UblSchemaValidator::class)->validateInvoice($payload->contents);

        $this->assertTrue($validation->valid, json_encode($validation->errors, JSON_PRETTY_PRINT));
        $this->assertSame('20123456789-03-B001-1.xml', $payload->fileName);
        $this->assertXmlValues($payload->contents, '03', 'B001-00000001', '-');
    }

    public function test_filename_rejects_a_series_that_does_not_follow_verified_sunat_rules(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor, 'RECEIPT');
        $document = $this->generateReceipt($tenant, $store, $order, $actor);
        $document->setAttribute('series', 'INVALID');

        $this->expectException(\InvalidArgumentException::class);
        app(SunatDocumentFileName::class)->xml($document);
    }

    private function assertXmlValues(string $contents, string $type, string $id, string $customer): void
    {
        $xml = new DOMDocument;
        $this->assertTrue($xml->loadXML($contents, LIBXML_NONET));
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $this->assertSame('2.1', $xpath->evaluate('string(/i:Invoice/cbc:UBLVersionID)'));
        $this->assertSame('2.0', $xpath->evaluate('string(/i:Invoice/cbc:CustomizationID)'));
        $this->assertSame($id, $xpath->evaluate('string(/i:Invoice/cbc:ID)'));
        $this->assertSame($type, $xpath->evaluate('string(/i:Invoice/cbc:InvoiceTypeCode)'));
        $this->assertSame('PEN', $xpath->evaluate('string(/i:Invoice/cbc:DocumentCurrencyCode)'));
        $this->assertSame($customer, $xpath->evaluate('string(/i:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID)'));
        $this->assertSame('100.00', $xpath->evaluate('string(/i:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount)'));
    }
}
