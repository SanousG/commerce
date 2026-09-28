<?php

namespace Tests\Feature\TaxFiscal;

use App\Models\User;
use App\Modules\Fiscal\Domain\Models\FiscalDailySummary;
use App\Modules\Fiscal\Domain\Models\FiscalDailySummaryItem;
use App\Modules\Fiscal\Domain\Models\FiscalSubmission;
use Illuminate\Database\QueryException;

final class SunatDatabaseConstraintTest extends TaxFiscalFeatureTestCase
{
    public function test_only_one_submission_attempt_can_be_in_flight_while_terminal_history_is_preserved(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor);
        $document = $this->generateReceipt($tenant, $store, $order, $actor);
        $first = FiscalSubmission::query()->create([
            'tenant_id' => $tenant->getKey(), 'fiscal_document_id' => $document->getKey(),
            'attempt_number' => 1, 'status' => 'ERROR', 'provider' => 'SUNAT_DIRECT', 'environment' => 'BETA',
        ]);
        FiscalSubmission::query()->create([
            'tenant_id' => $tenant->getKey(), 'fiscal_document_id' => $document->getKey(),
            'attempt_number' => 2, 'status' => 'PENDING', 'provider' => 'SUNAT_DIRECT', 'environment' => 'BETA',
        ]);

        $this->assertDatabaseHas('fiscal_submissions', ['id' => $first->getKey(), 'status' => 'ERROR']);
        $this->expectException(QueryException::class);
        FiscalSubmission::query()->create([
            'tenant_id' => $tenant->getKey(), 'fiscal_document_id' => $document->getKey(),
            'attempt_number' => 3, 'status' => 'UNKNOWN', 'provider' => 'SUNAT_DIRECT', 'environment' => 'BETA',
        ]);
    }

    public function test_daily_summary_membership_cannot_duplicate_an_active_document(): void
    {
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$order] = $this->readyFiscalOrder($tenant, $store, $actor);
        $document = $this->generateReceipt($tenant, $store, $order, $actor);
        $first = $this->summary($tenant->getKey(), $store->getKey(), 1);
        FiscalDailySummaryItem::query()->create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(),
            'fiscal_daily_summary_id' => $first->getKey(), 'fiscal_document_id' => $document->getKey(),
            'status' => 'INCLUDED',
        ]);
        $second = $this->summary($tenant->getKey(), $store->getKey(), 2);

        $this->expectException(QueryException::class);
        FiscalDailySummaryItem::query()->create([
            'tenant_id' => $tenant->getKey(), 'store_id' => $store->getKey(),
            'fiscal_daily_summary_id' => $second->getKey(), 'fiscal_document_id' => $document->getKey(),
            'status' => 'INCLUDED',
        ]);
    }

    private function summary(string $tenantId, string $storeId, int $sequence): FiscalDailySummary
    {
        return FiscalDailySummary::query()->create([
            'tenant_id' => $tenantId, 'store_id' => $storeId,
            'summary_date' => now()->toDateString(), 'sequence' => $sequence, 'status' => 'DRAFT',
            'identifier' => 'RC-'.now()->format('Ymd').'-'.$sequence, 'environment' => 'BETA',
        ]);
    }
}
