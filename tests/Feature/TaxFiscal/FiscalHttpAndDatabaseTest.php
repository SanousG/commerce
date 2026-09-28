<?php

namespace Tests\Feature\TaxFiscal;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Fiscal\Application\GenerateFiscalDocument;
use App\Modules\Fiscal\Domain\Enums\FiscalSubmissionStatus;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalSeries;
use App\Modules\Fiscal\Domain\Models\FiscalSubmission;
use App\Modules\Tax\Domain\Models\TaxRate;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

class FiscalHttpAndDatabaseTest extends TaxFiscalFeatureTestCase
{
    public function test_http_generation_listing_and_show_are_store_scoped_with_member_read_only_access(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $owner);
        $this->useContext($owner, $tenant, $store);

        $this->post(route('admin.orders.fiscal-document.store', $order), [
            'document_type' => 'receipt',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $document = FiscalDocument::query()->sole();
        $this->get(route('admin.fiscal-documents.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/fiscal-documents/Index')->has('documents.data', 1)
            ->where('documents.data.0.id', $document->getKey()));
        $this->get(route('admin.fiscal-documents.show', $document))->assertOk();

        $otherStore = $this->createStore($tenant);
        $this->useContext($owner, $tenant, $otherStore);
        $this->get(route('admin.fiscal-documents.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('documents.data', 0));
        $this->get(route('admin.fiscal-documents.show', $document))->assertNotFound();

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $memberStore = $this->createStore($memberTenant);
        $this->useContext($member, $memberTenant, $memberStore);
        $this->get(route('admin.fiscal-documents.index'))->assertOk();
        $this->post(route('admin.orders.fiscal-document.store', $order), ['document_type' => 'RECEIPT'])->assertForbidden();
    }

    public function test_sunat_submission_and_artifact_routes_are_store_scoped_and_require_manage_permission(): void
    {
        $owner = User::factory()->create();
        $tenant = $this->createTenantFor($owner);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $owner, 'INVOICE');
        $document = app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, [
            'document_type' => 'INVOICE', 'customer_document_type' => 'RUC',
            'customer_document_number' => '20555555555', 'customer_name' => 'Buyer SAC',
            'customer_address' => 'Av. Buyer 456',
        ], $owner);
        $submission = FiscalSubmission::query()->create([
            'tenant_id' => $tenant->getKey(), 'fiscal_document_id' => $document->getKey(),
            'attempt_number' => 1, 'status' => 'ERROR', 'provider' => 'SUNAT_DIRECT',
        ]);

        $otherStore = $this->createStore($tenant);
        $this->useContext($owner, $tenant, $otherStore);
        $this->post(route('admin.fiscal-documents.submit-sunat', $document))->assertNotFound();
        $this->get(route('admin.fiscal-documents.artifacts.download', [$document, $submission, 'signed-xml']))->assertNotFound();

        $member = User::factory()->create();
        $memberTenant = $this->createTenantFor($member, SystemRole::Member);
        $memberStore = $this->createStore($memberTenant);
        $this->useContext($member, $memberTenant, $memberStore);
        $this->post(route('admin.fiscal-documents.submit-sunat', $document))->assertForbidden();
    }

    public function test_database_rejects_duplicate_primary_document_for_an_order(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor);
        $document = $this->generateReceipt($tenant, $store, $order, $actor);
        $duplicate = $document->replicate(['id']);
        $duplicate->full_number = 'B001-00009999';
        $duplicate->sequence_number = 9999;

        $this->expectException(QueryException::class);
        $duplicate->save();
    }

    public function test_database_rejects_out_of_range_tax_rate(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $category = $this->createTaxCategory($tenant);

        $this->expectException(QueryException::class);
        $this->createTaxRate($tenant, $category, '100.000001');
    }

    public function test_database_rejects_cross_tenant_tax_category_relationship(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $foreignTenant = $this->createTenantFor(User::factory()->create());
        $foreignCategory = $this->createTaxCategory($foreignTenant);

        $this->expectException(QueryException::class);
        TaxRate::query()->create([
            'tenant_id' => $tenant->getKey(),
            'tax_category_id' => $foreignCategory->getKey(),
            'name' => 'Cross tenant rate',
            'rate' => '18.000000',
            'valid_from' => null,
            'valid_until' => null,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_database_allows_only_one_active_series_per_store_and_document_type(): void
    {
        $tenant = $this->createTenantFor(User::factory()->create());
        $store = $this->createStore($tenant);
        $this->createFiscalSeries($store);

        $this->expectException(QueryException::class);
        FiscalSeries::query()->create([
            'tenant_id' => $tenant->getKey(),
            'store_id' => $store->getKey(),
            'document_type' => 'RECEIPT',
            'series' => 'B999',
            'number_padding' => 8,
            'next_number' => 1,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_fiscal_document_snapshot_fields_are_immutable(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor);
        $document = $this->generateReceipt($tenant, $store, $order, $actor);

        $this->expectException(QueryException::class);
        $document->update(['customer_name' => 'Mutated snapshot']);
    }

    public function test_fiscal_document_items_cannot_be_updated(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor);
        $item = $this->generateReceipt($tenant, $store, $order, $actor)->items()->sole();

        $this->expectException(QueryException::class);
        $item->update(['description' => 'Mutated item']);
    }

    public function test_credit_note_model_relationship_and_submission_fields_are_prepared(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor);
        $primary = $this->generateReceipt($tenant, $store, $order, $actor);
        $creditSeries = $this->createFiscalSeries($store, 'CREDIT_NOTE');

        $credit = $primary->replicate(['id']);
        $credit->forceFill([
            'fiscal_series_id' => $creditSeries->getKey(),
            'related_fiscal_document_id' => $primary->getKey(),
            'document_type' => 'CREDIT_NOTE',
            'series' => 'FC01',
            'sequence_number' => 1,
            'full_number' => 'FC01-00000001',
            'credit_note_reason_code' => '01',
            'credit_note_reason' => 'Order cancellation',
            'adjustment_amount' => $primary->grand_total,
        ])->save();

        $submission = FiscalSubmission::query()->create([
            'tenant_id' => $tenant->getKey(),
            'fiscal_document_id' => $credit->getKey(),
            'attempt_number' => 1,
            'status' => 'PENDING',
            'provider' => 'SUNAT',
            'request_hash' => hash('sha256', 'credit-note-payload'),
            'external_ticket' => null,
            'response_code' => null,
            'response_message' => null,
            'cdr_path' => null,
            'submitted_at' => null,
            'responded_at' => null,
        ]);

        $this->assertTrue($credit->fresh()->relatedDocument->is($primary));
        $this->assertTrue($primary->fresh()->creditNotes->sole()->is($credit));
        $this->assertSame(FiscalSubmissionStatus::Pending, $submission->status);
        $this->assertSame('SUNAT', $submission->provider);
        $this->assertSame(1, $submission->attempt_number);
    }

    public function test_generation_explicitly_rejects_credit_notes_without_side_effects(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order, , , , $series] = $this->readyFiscalOrder($tenant, $store, $actor);

        try {
            app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, [
                'document_type' => 'CREDIT_NOTE',
            ], $actor);
            $this->fail('Credit note issuance is outside this phase.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document_type', $exception->errors());
        }

        $this->assertSame(0, FiscalDocument::query()->count());
        $this->assertSame(1, $series->fresh()->next_number);
    }
}
