<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Modules\Analytics\Application\FiscalDashboardMetrics;
use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalSubmissionStatus;
use App\Modules\Fiscal\Domain\Models\FiscalSubmission;
use Carbon\CarbonImmutable;
use Tests\Feature\TaxFiscal\TaxFiscalFeatureTestCase;

class FiscalDashboardMetricsTest extends TaxFiscalFeatureTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_statuses_and_attention_use_store_scope_without_double_counting_unknown(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 18:00:00 UTC');
        $actor = User::factory()->create();
        $tenant = $this->createTenantFor($actor);
        $store = $this->createStore($tenant);
        [$firstOrder] = $this->readyFiscalOrder($tenant, $store, $actor);
        $documents = [$this->generateReceipt($tenant, $store, $firstOrder, $actor)];
        foreach (range(1, 3) as $_) {
            [$order] = $this->readyOrder($tenant, $store, $actor, 1);
            $documents[] = $this->generateReceipt($tenant, $store, $order, $actor);
        }
        foreach ([
            FiscalDocumentStatus::Generated,
            FiscalDocumentStatus::Submitted,
            FiscalDocumentStatus::Accepted,
            FiscalDocumentStatus::Rejected,
        ] as $index => $status) {
            $documents[$index]->update(['status' => $status]);
        }
        FiscalSubmission::create([
            'tenant_id' => $tenant->getKey(),
            'fiscal_document_id' => $documents[1]->getKey(),
            'attempt_number' => 1,
            'status' => FiscalSubmissionStatus::Unknown,
            'provider' => 'SUNAT_DIRECT',
            'environment' => 'BETA',
            'submitted_at' => now(),
        ]);

        $metrics = app(FiscalDashboardMetrics::class)->build(
            $tenant->getKey(),
            $store->getKey(),
            DashboardDateRange::make(DashboardRange::Today, $store->timezone),
        );

        $this->assertSame(1, $metrics['by_status']['GENERATED']);
        $this->assertSame(1, $metrics['by_status']['SUBMITTED']);
        $this->assertSame(1, $metrics['by_status']['ACCEPTED']);
        $this->assertSame(1, $metrics['by_status']['REJECTED']);
        $this->assertSame(1, $metrics['unknown_submissions']);
        $this->assertSame(3, $metrics['attention']);
        $this->assertTrue($metrics['configured']);
        $this->assertCount(1, $metrics['rejected_documents']);
    }
}
