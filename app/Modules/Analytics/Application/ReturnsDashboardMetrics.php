<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Returns\Domain\Enums\ExchangeStatus;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use Illuminate\Support\Facades\DB;

final class ReturnsDashboardMetrics
{
    /** @return array<string, mixed> */
    public function build(string $tenantId, string $storeId, bool $includeExchanges): array
    {
        $openStatuses = [
            ReturnStatus::Requested->value,
            ReturnStatus::Approved->value,
            ReturnStatus::Received->value,
            ReturnStatus::Inspecting->value,
        ];

        $statusCounts = DB::table('returns')->where('tenant_id', $tenantId)->where('store_id', $storeId)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $data = [
            'by_status' => collect(ReturnStatus::cases())->mapWithKeys(
                fn (ReturnStatus $status) => [$status->value => (int) ($statusCounts[$status->value] ?? 0)]
            )->all(),
            'open' => array_sum(array_map(fn (string $status) => (int) ($statusCounts[$status] ?? 0), $openStatuses)),
            'awaiting_inspection' => (int) ($statusCounts[ReturnStatus::Received->value] ?? 0)
                + (int) ($statusCounts[ReturnStatus::Inspecting->value] ?? 0),
            'restock_pending' => DB::table('returns')->where('returns.tenant_id', $tenantId)->where('returns.store_id', $storeId)
                ->whereIn('returns.status', $openStatuses)
                ->whereExists(fn ($query) => $query->selectRaw('1')->from('return_items')
                    ->whereColumn('return_items.return_id', 'returns.id')
                    ->whereColumn('return_items.tenant_id', 'returns.tenant_id')
                    ->where('return_items.disposition', ReturnItemDisposition::Restock->value)
                    ->whereColumn('return_items.restocked_quantity', '<', 'return_items.accepted_quantity'))
                ->count(),
        ];

        if ($includeExchanges) {
            $data['pending_exchanges'] = DB::table('exchanges')
                ->where('tenant_id', $tenantId)->where('store_id', $storeId)
                ->whereIn('status', [ExchangeStatus::Pending->value, ExchangeStatus::Approved->value])
                ->count();
        }

        return $data;
    }
}
