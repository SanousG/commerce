<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Returns\Domain\Enums\ExchangeStatus;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use Illuminate\Support\Facades\DB;

final class ReturnsDashboardMetrics
{
    /** @return array<string, int> */
    public function build(string $tenantId, string $storeId, bool $includeExchanges): array
    {
        $openStatuses = [
            ReturnStatus::Requested->value,
            ReturnStatus::Approved->value,
            ReturnStatus::Received->value,
            ReturnStatus::Inspecting->value,
        ];

        $data = [
            'open' => DB::table('returns')->where('tenant_id', $tenantId)->where('store_id', $storeId)
                ->whereIn('status', $openStatuses)->count(),
            'awaiting_inspection' => DB::table('returns')->where('tenant_id', $tenantId)->where('store_id', $storeId)
                ->whereIn('status', [ReturnStatus::Received->value, ReturnStatus::Inspecting->value])->count(),
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
