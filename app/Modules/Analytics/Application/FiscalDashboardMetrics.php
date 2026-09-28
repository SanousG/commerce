<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Fiscal\Domain\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalSubmissionStatus;
use Illuminate\Support\Facades\DB;

final class FiscalDashboardMetrics
{
    /** @return array<string, mixed> */
    public function build(string $tenantId, string $storeId, DashboardDateRange $range): array
    {
        $statuses = DB::table('fiscal_documents')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('issue_date', '>=', $range->localStart->toDateString())
            ->where('issue_date', '<', $range->localEnd->toDateString())
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $unknownDocumentIds = DB::table('fiscal_submissions')
            ->join('fiscal_documents', function ($join) {
                $join->on('fiscal_documents.id', '=', 'fiscal_submissions.fiscal_document_id')
                    ->on('fiscal_documents.tenant_id', '=', 'fiscal_submissions.tenant_id');
            })
            ->where('fiscal_submissions.tenant_id', $tenantId)
            ->where('fiscal_documents.store_id', $storeId)
            ->where('fiscal_submissions.status', FiscalSubmissionStatus::Unknown->value)
            ->distinct()
            ->pluck('fiscal_submissions.fiscal_document_id');
        $unknown = $unknownDocumentIds->count();

        $attentionStatuses = [
            FiscalDocumentStatus::Generated->value,
            FiscalDocumentStatus::SubmissionPending->value,
            FiscalDocumentStatus::Submitted->value,
            FiscalDocumentStatus::Rejected->value,
        ];

        $rejected = DB::table('fiscal_documents')
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'fiscal_documents.order_id')
                    ->on('orders.tenant_id', '=', 'fiscal_documents.tenant_id')
                    ->on('orders.store_id', '=', 'fiscal_documents.store_id');
            })
            ->where('fiscal_documents.tenant_id', $tenantId)
            ->where('fiscal_documents.store_id', $storeId)
            ->where('fiscal_documents.status', FiscalDocumentStatus::Rejected->value)
            ->orderByDesc('fiscal_documents.rejected_at')
            ->limit(5)
            ->get([
                'fiscal_documents.id', 'fiscal_documents.full_number', 'fiscal_documents.status',
                'fiscal_documents.rejected_at', 'orders.order_number', 'orders.customer_name',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'number' => $row->full_number,
                'order' => $row->order_number,
                'customer' => $row->customer_name,
                'status' => $row->status,
                'date' => $row->rejected_at,
            ])->all();

        $configured = DB::table('fiscal_issuer_profiles')
            ->where('tenant_id', $tenantId)->where('status', 'ACTIVE')->exists()
            && DB::table('fiscal_series')
                ->where('tenant_id', $tenantId)->where('store_id', $storeId)->where('status', 'ACTIVE')->exists();

        return [
            'by_status' => collect(FiscalDocumentStatus::cases())->mapWithKeys(
                fn (FiscalDocumentStatus $status) => [$status->value => (int) ($statuses[$status->value] ?? 0)]
            )->all(),
            'attention' => DB::table('fiscal_documents')
                ->where('tenant_id', $tenantId)->where('store_id', $storeId)
                ->where(function ($query) use ($attentionStatuses, $unknownDocumentIds) {
                    $query->whereIn('status', $attentionStatuses);
                    if ($unknownDocumentIds->isNotEmpty()) {
                        $query->orWhereIn('id', $unknownDocumentIds);
                    }
                })->count(),
            'unknown_submissions' => $unknown,
            'rejected_documents' => $rejected,
            'configured' => $configured,
            'environment' => (string) config('sunat.environment', 'BETA'),
            'production_enabled' => (bool) config('sunat.production_enabled', false),
        ];
    }
}
