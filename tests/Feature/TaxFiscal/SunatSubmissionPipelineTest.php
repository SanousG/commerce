<?php

namespace Tests\Feature\TaxFiscal;

use App\Models\User;
use App\Modules\Cart\Application\AddCartItem;
use App\Modules\Cart\Application\CreateCart;
use App\Modules\Checkout\Application\PrepareCheckout;
use App\Modules\Checkout\Application\StartCheckout;
use App\Modules\Checkout\Application\UpdateCheckout;
use App\Modules\Fiscal\Application\Contracts\DigitalCertificateProvider;
use App\Modules\Fiscal\Application\Contracts\FiscalArtifactStorage;
use App\Modules\Fiscal\Application\Contracts\SunatTransport;
use App\Modules\Fiscal\Application\Data\DigitalCertificate;
use App\Modules\Fiscal\Application\Data\SunatTransportResult;
use App\Modules\Fiscal\Application\GenerateFiscalDocument;
use App\Modules\Fiscal\Application\SubmitFiscalDocumentToSunat;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalSubmissionStatus;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalSubmission;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatDeliveryPhase;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatTransportException;
use App\Modules\Fiscal\Infrastructure\Sunat\Ubl21FiscalDocumentSerializer;
use App\Modules\Listings\Application\SetStoreDefaultPriceList;
use App\Modules\Listings\Domain\Enums\StoreListingStatus;
use App\Modules\Orders\Application\CreateOrderFromCheckout;
use App\Modules\Pricing\Application\CreatePriceList;
use App\Modules\Stores\Domain\Models\Store;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Fakes\FakeFiscalArtifactStorage;
use Tests\Fakes\FakeSunatTransport;
use ZipArchive;

final class SunatSubmissionPipelineTest extends TaxFiscalFeatureTestCase
{
    /** @var array<string, DigitalCertificate> */
    private static array $certificates = [];

    private FakeSunatTransport $transport;

    private FakeFiscalArtifactStorage $artifacts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new FakeSunatTransport;
        $this->artifacts = new FakeFiscalArtifactStorage;
        $this->app->instance(SunatTransport::class, $this->transport);
        $this->app->instance(FiscalArtifactStorage::class, $this->artifacts);
        $this->app->instance(DigitalCertificateProvider::class, $this->certificateProvider($this->certificate()));
        config()->set('sunat.environment', 'BETA');
        config()->set('sunat.credentials.username', 'MODDATOS');
        config()->set('sunat.credentials.password', 'MODDATOS');
    }

    public function test_accepted_cdr_marks_submission_and_document_accepted_with_stored_artifacts(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $this->transport->script([new SunatTransportResult(cdrZip: $this->cdrZip('cdr-accepted.xml'))]);

        $submission = $this->submit($tenant, $store, $document);

        $this->assertSame(FiscalSubmissionStatus::Accepted, $submission->status);
        $this->assertSame('0', $submission->response_code);
        $this->assertSame(FiscalDocumentStatus::Accepted, $document->fresh()->status);
        $this->assertNotNull($submission->responded_at);
        $this->assertNotNull($submission->duration_ms);
        $this->assertSame(1, $this->transport->sendBillCalls);
        $this->assertSame($tenant->getKey(), $this->transport->requests[0]['tenant_id']);
        $this->assertSame('20123456789-01-F001-1.zip', $this->transport->requests[0]['name']);
        $this->assertNotNull($submission->cdr_storage_key);
        $this->assertNotNull($submission->cdr_xml_storage_key);
        $signedXml = $this->artifacts->get($tenant->getKey(), (string) $submission->signed_xml_storage_key);
        $this->assertSame(hash('sha256', $signedXml), $submission->request_hash);
        $this->assertStringNotContainsString('MODDATOS', $signedXml);
    }

    public function test_accepted_cdr_with_observations_is_accepted_and_preserves_notes(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $this->transport->script([new SunatTransportResult(cdrZip: $this->cdrZip('cdr-observation.xml'))]);

        $submission = $this->submit($tenant, $store, $document);

        $this->assertSame(FiscalSubmissionStatus::Accepted, $submission->status);
        $this->assertSame(['Observacion fixture'], $submission->observations);
        $this->assertSame(FiscalDocumentStatus::Accepted, $document->fresh()->status);
    }

    public function test_rejected_cdr_marks_rejected_and_blocks_resubmission(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $this->transport->script([new SunatTransportResult(cdrZip: $this->cdrZip('cdr-rejected.xml'))]);

        $submission = $this->submit($tenant, $store, $document);

        $this->assertSame(FiscalSubmissionStatus::Rejected, $submission->status);
        $this->assertSame('2000', $submission->response_code);
        $this->assertSame(FiscalDocumentStatus::Rejected, $document->fresh()->status);

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));
        $this->assertInstanceOf(ValidationException::class, $caught);
        $this->assertSame(
            'A SUNAT-rejected document cannot be resubmitted automatically.',
            $caught->errors()['document'][0] ?? null,
        );
        $this->assertSame(1, $this->transport->sendBillCalls);
        $this->assertSame(1, FiscalSubmission::query()->count());
    }

    public function test_malformed_cdr_blocks_blind_resend_as_unknown(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $this->transport->script([new SunatTransportResult(cdrZip: $this->cdrZip('cdr-malformed.xml'))]);

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));

        $this->assertInstanceOf(RuntimeException::class, $caught);
        $this->assertSame('SUNAT_CDR_REQUIRED_FIELDS_MISSING', $caught->getMessage());
        $submission = FiscalSubmission::query()->sole();
        $this->assertSame(FiscalSubmissionStatus::Unknown, $submission->status);
        $this->assertSame(FiscalDocumentStatus::Submitted, $document->fresh()->status);
        $this->assertSame(1, $this->transport->sendBillCalls);

        $second = $this->capture(fn () => $this->submit($tenant, $store, $document));
        $this->assertInstanceOf(ValidationException::class, $second);
        $this->assertSame(1, $this->transport->sendBillCalls);
    }

    public function test_pre_delivery_failure_is_retryable_and_keeps_document_generated(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $this->transport->script([
            new SunatTransportException('SUNAT_TRANSPORT_FAILED', SunatDeliveryPhase::PreDelivery, null, 'Could not connect to host'),
            new SunatTransportResult(cdrZip: $this->cdrZip('cdr-accepted.xml')),
        ]);

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));

        $this->assertInstanceOf(SunatTransportException::class, $caught);
        $first = FiscalSubmission::query()->sole();
        $this->assertSame(FiscalSubmissionStatus::Error, $first->status);
        $this->assertSame('Could not connect to host', $first->response_message);
        $this->assertSame(SunatTransportException::class, $first->error_class);
        $this->assertSame(FiscalDocumentStatus::Generated, $document->fresh()->status);

        $retry = $this->submit($tenant, $store, $document);

        $this->assertSame(FiscalSubmissionStatus::Accepted, $retry->status);
        $this->assertSame(2, $retry->attempt_number);
        $this->assertSame($first->request_hash, $retry->request_hash);
        $this->assertSame(2, $this->transport->sendBillCalls);
        $this->assertSame(2, FiscalSubmission::query()->count());
        $this->assertSame(FiscalDocumentStatus::Accepted, $document->fresh()->status);
    }

    public function test_remote_sunat_fault_is_retryable_and_records_sanitized_detail(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $fault = '1033 - La cuenta SOL no tiene acceso al servicio';
        $this->transport->script([new SunatTransportException('SUNAT_TRANSPORT_FAILED', SunatDeliveryPhase::RemoteResponse, null, $fault)]);

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));

        $this->assertInstanceOf(SunatTransportException::class, $caught);
        $submission = FiscalSubmission::query()->sole();
        $this->assertSame(FiscalSubmissionStatus::Error, $submission->status);
        $this->assertSame($fault, $submission->response_message);
        $this->assertSame(FiscalDocumentStatus::Generated, $document->fresh()->status);
        $this->assertSame(1, $this->transport->sendBillCalls);
    }

    public function test_unknown_delivery_blocks_resend_and_submits_document(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $this->transport->script([new SunatTransportException('SUNAT_TRANSPORT_FAILED', SunatDeliveryPhase::DeliveryUnknown, null, 'Connection reset by peer')]);

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));

        $this->assertInstanceOf(SunatTransportException::class, $caught);
        $submission = FiscalSubmission::query()->sole();
        $this->assertSame(FiscalSubmissionStatus::Unknown, $submission->status);
        $this->assertSame(FiscalDocumentStatus::Submitted, $document->fresh()->status);
        $this->assertNotNull($document->fresh()->submitted_at);

        $second = $this->capture(fn () => $this->submit($tenant, $store, $document));
        $this->assertInstanceOf(ValidationException::class, $second);
        $this->assertSame(1, $this->transport->sendBillCalls);
        $this->assertSame(1, FiscalSubmission::query()->count());
    }

    public function test_local_preparation_failure_never_calls_transport_and_keeps_document_generated(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $this->app->instance(FiscalArtifactStorage::class, new class implements FiscalArtifactStorage
        {
            public function put(string $tenantId, string $documentId, string $directory, string $fileName, string $contents): string
            {
                throw new RuntimeException('SUNAT_STORAGE_UNAVAILABLE');
            }

            public function get(string $tenantId, string $storageKey): string
            {
                throw new RuntimeException('SUNAT_STORAGE_UNAVAILABLE');
            }
        });

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));

        $this->assertInstanceOf(RuntimeException::class, $caught);
        $this->assertSame('SUNAT_STORAGE_UNAVAILABLE', $caught->getMessage());
        $submission = FiscalSubmission::query()->sole();
        $this->assertSame(FiscalSubmissionStatus::Error, $submission->status);
        $this->assertSame('SUNAT_STORAGE_UNAVAILABLE', $submission->response_message);
        $this->assertNull($submission->responded_at);
        $this->assertSame(FiscalDocumentStatus::Generated, $document->fresh()->status);
        $this->assertSame(0, $this->transport->sendBillCalls);
    }

    public function test_in_flight_claim_returns_existing_submission_without_network_call(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $document->update(['status' => FiscalDocumentStatus::SubmissionPending->value]);
        $existing = FiscalSubmission::query()->create([
            'tenant_id' => $tenant->getKey(),
            'fiscal_document_id' => $document->getKey(),
            'attempt_number' => 1,
            'status' => FiscalSubmissionStatus::Processing->value,
            'provider' => 'SUNAT_DIRECT',
            'environment' => 'BETA',
            'request_hash' => hash('sha256', 'in-flight'),
        ]);

        $submission = $this->submit($tenant, $store, $document);

        $this->assertTrue($submission->is($existing));
        $this->assertSame(0, $this->transport->sendBillCalls);
        $this->assertSame(1, FiscalSubmission::query()->count());
        $this->assertSame(FiscalDocumentStatus::SubmissionPending, $document->fresh()->status);
    }

    public function test_cross_tenant_submission_cannot_resolve_or_touch_a_foreign_document(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $intruder = User::factory()->create();
        $intruderTenant = $this->createTenantFor($intruder);
        $intruderStore = $this->createStore($intruderTenant);

        $foreignTenant = $this->capture(fn () => $this->submit($intruderTenant, $intruderStore, $document));
        $foreignStore = $this->capture(fn () => $this->submit($tenant, $intruderStore, $document));

        $this->assertInstanceOf(ModelNotFoundException::class, $foreignTenant);
        $this->assertInstanceOf(ModelNotFoundException::class, $foreignStore);
        $this->assertSame(0, $this->transport->sendBillCalls);
        $this->assertSame(0, FiscalSubmission::query()->count());
        $this->assertSame(FiscalDocumentStatus::Generated, $document->fresh()->status);
    }

    public function test_non_pen_currency_is_blocked_before_any_side_effect(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant, ['currency' => 'USD']);
        $usdList = app(CreatePriceList::class)->handle($tenant, [
            'name' => 'USD prices', 'code' => 'USD-SUNAT', 'currency' => 'USD',
        ]);
        app(SetStoreDefaultPriceList::class)->handle($tenant, $store, $usdList->getKey());

        $category = $this->createTaxCategory($tenant);
        $this->createTaxRate($tenant, $category);
        $cart = app(CreateCart::class)->handle($store);
        $product = $this->createProduct($tenant);
        $product->update(['tax_category_id' => $category->getKey()]);
        $variant = $product->variants()->sole();
        $listing = $this->createListing($store, $product);
        $listing->update(['status' => StoreListingStatus::Published, 'published_at' => now()]);
        $this->priceVariant($tenant, $usdList, $variant, '100.0000');
        $this->stock($tenant, $variant, 5);
        app(AddCartItem::class)->handle($cart, $listing->fresh(), $variant, 1);

        $checkout = app(StartCheckout::class)->handle($tenant, $store, $cart->fresh(), (string) Str::uuid());
        app(UpdateCheckout::class)->handle($tenant, $checkout, [
            'customer_name' => 'USD Buyer', 'email' => 'usd@example.test', 'delivery_type' => 'PICKUP',
        ]);
        $ready = app(PrepareCheckout::class)->handle($tenant, $checkout->fresh());
        $order = app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready, $actor);
        $this->createIssuer($tenant);
        $this->createFiscalSeries($store, 'INVOICE');
        $document = app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, [
            'document_type' => 'INVOICE',
            'customer_document_type' => 'RUC',
            'customer_document_number' => '20555555555',
            'customer_name' => 'Buyer SAC',
            'customer_address' => 'Av. Buyer 456',
        ], $actor);

        $this->assertSame('USD', $document->currency);

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));

        $this->assertInstanceOf(ValidationException::class, $caught);
        $this->assertSame('SUNAT_CURRENCY_NOT_SUPPORTED', $caught->errors()['document'][0] ?? null);
        $this->assertSame(0, FiscalSubmission::query()->count());
        $this->assertSame(0, $this->transport->sendBillCalls);
        $this->assertSame(FiscalDocumentStatus::Generated, $document->fresh()->status);
    }

    public function test_multi_line_document_reconciles_line_extension_and_tax_totals(): void
    {
        [, $tenant, $store, $document] = $this->invoiceWithPrices(['50.0000', '50.0000']);
        $payload = app(Ubl21FiscalDocumentSerializer::class)->buildPayload($document);
        $xpath = $this->xpath($payload->contents);

        $this->assertSame('84.74', $xpath->evaluate('string(/i:Invoice/cac:LegalMonetaryTotal/cbc:LineExtensionAmount)'));
        $this->assertSame('84.74', $xpath->evaluate('string(/i:Invoice/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount)'));
        $this->assertSame('100.00', $xpath->evaluate('string(/i:Invoice/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount)'));
        $this->assertSame('100.00', $xpath->evaluate('string(/i:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount)'));
        $this->assertSame('15.26', $xpath->evaluate('string(/i:Invoice/cac:TaxTotal/cbc:TaxAmount)'));
        $this->assertSame('84.74', $xpath->evaluate('string(/i:Invoice/cac:TaxTotal/cac:TaxSubtotal[1]/cbc:TaxableAmount)'));
        $this->assertSame('15.26', $xpath->evaluate('string(/i:Invoice/cac:TaxTotal/cac:TaxSubtotal[1]/cbc:TaxAmount)'));
        $this->assertSame('42.37', $xpath->evaluate('string(/i:Invoice/cac:InvoiceLine[1]/cbc:LineExtensionAmount)'));
        $this->assertSame('7.63', $xpath->evaluate('string(/i:Invoice/cac:InvoiceLine[1]/cac:TaxTotal/cbc:TaxAmount)'));
        $this->assertSame('42.37', $xpath->evaluate('string(/i:Invoice/cac:InvoiceLine[2]/cbc:LineExtensionAmount)'));
        $this->assertSame('7.63', $xpath->evaluate('string(/i:Invoice/cac:InvoiceLine[2]/cac:TaxTotal/cbc:TaxAmount)'));

        $this->transport->script([new SunatTransportResult(cdrZip: $this->cdrZip('cdr-accepted.xml'))]);
        $submission = $this->submit($tenant, $store, $document);

        $this->assertSame(FiscalSubmissionStatus::Accepted, $submission->status);
    }

    public function test_rounding_drift_between_line_totals_and_document_total_blocks_submission(): void
    {
        [, $tenant, $store, $document] = $this->invoiceWithPrices(['16.6667', '16.6667']);

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));

        $this->assertInstanceOf(ValidationException::class, $caught);
        $this->assertSame('SUNAT_UBL_ROUNDING_DRIFT_NOT_RECONCILED', $caught->errors()['document'][0] ?? null);
        $this->assertSame(0, FiscalSubmission::query()->count());
        $this->assertSame(0, $this->transport->sendBillCalls);
        $this->assertSame(FiscalDocumentStatus::Generated, $document->fresh()->status);
    }

    public function test_certificate_ruc_mismatch_blocks_submission_before_signing(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        $this->app->instance(DigitalCertificateProvider::class, $this->certificateProvider($this->certificate('20999999999')));

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));

        $this->assertInstanceOf(ValidationException::class, $caught);
        $this->assertSame('SUNAT_CERTIFICATE_RUC_MISMATCH', $caught->errors()['document'][0] ?? null);
        $this->assertSame(0, FiscalSubmission::query()->count());
        $this->assertSame(0, $this->transport->sendBillCalls);
    }

    public function test_sol_username_ruc_mismatch_blocks_submission_before_signing(): void
    {
        [, $tenant, $store, $document] = $this->invoice();
        config()->set('sunat.credentials.username', 'SOL20999999999');

        $caught = $this->capture(fn () => $this->submit($tenant, $store, $document));

        $this->assertInstanceOf(ValidationException::class, $caught);
        $this->assertSame('SUNAT_SOL_USERNAME_RUC_MISMATCH', $caught->errors()['document'][0] ?? null);
        $this->assertSame(0, FiscalSubmission::query()->count());
        $this->assertSame(0, $this->transport->sendBillCalls);
    }

    public function test_submit_route_is_rate_limited_after_ten_attempts_per_user(): void
    {
        [$actor, $tenant, $store, $document] = $this->invoice();
        $this->useContext($actor, $tenant, $store);
        $this->transport->script([new SunatTransportResult(cdrZip: $this->cdrZip('cdr-accepted.xml'))]);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post(route('admin.fiscal-documents.submit-sunat', $document))->assertRedirect();
        }

        $this->post(route('admin.fiscal-documents.submit-sunat', $document))->assertStatus(429);
    }

    public function test_provisioned_xsd_tree_matches_committed_manifest(): void
    {
        $this->artisan('sunat:provision-schemas --verify')->assertExitCode(0);
    }

    private function submit(Tenant $tenant, $store, FiscalDocument $document): FiscalSubmission
    {
        return app(SubmitFiscalDocumentToSunat::class)
            ->handle($tenant->getKey(), (string) $store->getKey(), $document->getKey());
    }

    private function capture(callable $callback): ?\Throwable
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            return $exception;
        }

        return null;
    }

    /** @return array{0: User, 1: Tenant, 2: Store, 3: FiscalDocument} */
    private function invoice(): array
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor, 'INVOICE');
        $document = app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, [
            'document_type' => 'INVOICE',
            'customer_document_type' => 'RUC',
            'customer_document_number' => '20555555555',
            'customer_name' => 'Buyer SAC',
            'customer_address' => 'Av. Buyer 456',
        ], $actor);

        return [$actor, $tenant, $store, $document];
    }

    /** @param list<string> $prices @return array{0: User, 1: Tenant, 2: \App\Modules\Stores\Domain\Models\Store, 3: FiscalDocument} */
    private function invoiceWithPrices(array $prices): array
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        $category = $this->createTaxCategory($tenant);
        $this->createTaxRate($tenant, $category);
        $cart = app(CreateCart::class)->handle($store);

        foreach ($prices as $price) {
            $product = $this->createProduct($tenant);
            $product->update(['tax_category_id' => $category->getKey()]);
            $variant = $product->variants()->sole();
            $listing = $this->createListing($store, $product);
            $listing->update(['status' => StoreListingStatus::Published, 'published_at' => now()]);
            $this->priceVariant($tenant, $tenant->defaultPriceList()->sole(), $variant, $price);
            $this->stock($tenant, $variant, 5);
            app(AddCartItem::class)->handle($cart, $listing->fresh(), $variant, 1);
        }

        $checkout = app(StartCheckout::class)->handle($tenant, $store, $cart->fresh(), (string) Str::uuid());
        app(UpdateCheckout::class)->handle($tenant, $checkout, [
            'customer_name' => 'Multi Buyer', 'email' => 'multi@example.test', 'delivery_type' => 'PICKUP',
        ]);
        $ready = app(PrepareCheckout::class)->handle($tenant, $checkout->fresh());
        $order = app(CreateOrderFromCheckout::class)->handle($tenant, $store, $ready, $actor);
        $this->createIssuer($tenant);
        $this->createFiscalSeries($store, 'INVOICE');
        $document = app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, [
            'document_type' => 'INVOICE',
            'customer_document_type' => 'RUC',
            'customer_document_number' => '20555555555',
            'customer_name' => 'Multi Buyer SAC',
            'customer_address' => 'Av. Multi 789',
        ], $actor);

        return [$actor, $tenant, $store, $document];
    }

    private function cdrZip(string $fixture): string
    {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/Sunat/'.$fixture));
        $path = tempnam(sys_get_temp_dir(), 'sunat-cdr-test-');
        file_put_contents($path, '');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('R-20123456789-01-F001-1.xml', $xml);
        $this->assertTrue($zip->close());
        $contents = (string) file_get_contents($path);
        unlink($path);

        return $contents;
    }

    private function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($xml, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        return $xpath;
    }

    private function certificate(?string $subjectRuc = null): DigitalCertificate
    {
        $cacheKey = $subjectRuc ?? 'fixture';
        if (isset(self::$certificates[$cacheKey])) {
            return self::$certificates[$cacheKey];
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => $subjectRuc ?? 'SUNAT fixture'], $key, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        openssl_x509_export($x509, $certificatePem);
        openssl_pkey_export($key, $privateKeyPem);

        return self::$certificates[$cacheKey] = new DigitalCertificate(
            (string) $certificatePem,
            (string) $privateKeyPem,
            CarbonImmutable::now()->subMinute(),
            CarbonImmutable::now()->addDay(),
            strtolower((string) openssl_x509_fingerprint((string) $certificatePem, 'sha256')),
            $subjectRuc,
        );
    }

    private function certificateProvider(DigitalCertificate $certificate): DigitalCertificateProvider
    {
        return new class($certificate) implements DigitalCertificateProvider
        {
            public function __construct(private DigitalCertificate $certificate) {}

            public function certificateFor(string $tenantId): DigitalCertificate
            {
                return $this->certificate;
            }
        };
    }
}
