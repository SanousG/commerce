<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use Illuminate\Support\Facades\DB;

final class RecentOrdersDashboardMetrics
{
    private const LIMIT = 10;

    /**
     * Latest orders placed inside the selected window, newest first.
     *
     * Payment and fulfillment columns are included only when the caller holds
     * the matching domain view permission, so unauthorized financial context
     * never reaches the Inertia payload.
     *
     * @return list<array<string, mixed>>
     */
    public function build(
        string $tenantId,
        string $storeId,
        string $currency,
        DashboardDateRange $range,
        bool $includePaymentStatus,
        bool $includeFulfillmentStatus,
    ): array {
        return DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('currency', $currency)
            ->where('placed_at', '>=', $range->utcStart)
            ->where('placed_at', '<', $range->utcEnd)
            ->orderByDesc('placed_at')
            ->orderByDesc('order_number')
            ->limit(self::LIMIT)
            ->select([
                'id',
                'order_number',
                'customer_name',
                'grand_total',
                'status',
                'payment_status',
                'fulfillment_status',
            ])
            ->selectRaw(
                "TO_CHAR(timezone(?, placed_at), 'YYYY-MM-DD HH24:MI') AS placed_local",
                [$range->localStart->timezoneName],
            )
            ->get()
            ->map(function ($row) use ($includePaymentStatus, $includeFulfillmentStatus) {
                $order = [
                    'id' => $row->id,
                    'number' => $row->order_number,
                    'customer' => $row->customer_name,
                    'placed_at' => $row->placed_local,
                    'total' => DashboardMoney::value($row->grand_total),
                    'status' => $row->status,
                ];

                if ($includePaymentStatus) {
                    $order['payment_status'] = $row->payment_status;
                }

                if ($includeFulfillmentStatus) {
                    $order['fulfillment_status'] = $row->fulfillment_status;
                }

                return $order;
            })->all();
    }
}
