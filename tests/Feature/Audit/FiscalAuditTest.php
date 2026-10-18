<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Models\AuditEntry;
use App\Modules\Fiscal\Application\GenerateFiscalDocument;
use App\Modules\Fiscal\Domain\Models\FiscalDocument;
use Illuminate\Validation\ValidationException;
use Tests\Feature\TaxFiscal\TaxFiscalFeatureTestCase;

class FiscalAuditTest extends TaxFiscalFeatureTestCase
{
    public function test_fiscal_generation_is_idempotent_and_records_one_store_scoped_audit(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order, , , , $series] = $this->readyFiscalOrder($tenant, $store, $actor);

        $first = $this->generateReceipt($tenant, $store, $order, $actor);
        $retry = $this->generateReceipt($tenant, $store, $order, $actor);

        $this->assertSame($first->getKey(), $retry->getKey());
        $audit = AuditEntry::query()->where('action', AuditAction::FiscalDocumentIssued)->sole();
        $this->assertSame($first->getKey(), $audit->subject_id);
        $this->assertSame($first->full_number, $audit->subject_label);
        $this->assertSame($store->getKey(), $audit->store_id);
        $this->assertSame(['status' => 'GENERATED'], $audit->metadata);
        $this->assertSame('fiscal.generated:'.$first->getKey(), $audit->idempotency_key);
        $this->assertSame(1, FiscalDocument::query()->count());
        $this->assertSame(2, $series->fresh()->next_number);
    }

    public function test_failed_fiscal_generation_has_no_document_sequence_or_audit_side_effect(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order, , , , $series] = $this->readyFiscalOrder($tenant, $store, $actor, 'INVOICE');
        $before = AuditEntry::query()->where('action', AuditAction::FiscalDocumentIssued)->count();

        try {
            app(GenerateFiscalDocument::class)->handle($tenant, $store, $order, [
                'document_type' => 'INVOICE',
            ], $actor);
            $this->fail('An invoice without legal customer details was generated.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_document_type', $exception->errors());
        }

        $this->assertSame(0, FiscalDocument::query()->count());
        $this->assertSame(1, $series->fresh()->next_number);
        $this->assertSame($before, AuditEntry::query()->where('action', AuditAction::FiscalDocumentIssued)->count());
    }
}
