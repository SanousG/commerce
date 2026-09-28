<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Fulfillment\Domain\Enums\ShipmentStatus;
use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;

final class OperationsDashboardMetrics
{
    /** @return array<string, int> */
    public function fulfillment(string $tenantId, string $storeId, string $currency, DashboardDateRange $range): array
    {
        $counts = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('currency', $currency)
            ->where('status', '<>', OrderStatus::Cancelled->value)
            ->where('placed_at', '>=', $range->utcStart)
            ->where('placed_at', '<', $range->utcEnd)
            ->selectRaw('fulfillment_status, COUNT(*) AS aggregate')
            ->groupBy('fulfillment_status')
            ->pluck('aggregate', 'fulfillment_status');

        return collect(OrderFulfillmentStatus::cases())->mapWithKeys(
            fn (OrderFulfillmentStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)]
        )->all();
    }

    /** @return array<string, int> */
    public function fulfillmentPending(string $tenantId, string $storeId): int
    {
        return DB::table('fulfillments')
            ->where('tenant_id', $tenantId)->where('store_id', $storeId)
            ->whereIn('status', [FulfillmentStatus::Pending->value, FulfillmentStatus::Processing->value])
            ->count();
    }

    /** @return array<string, int> */
    public function shipmentAttention(string $tenantId, string $storeId): array
    {
        return [
            'shipments_pending_or_ready' => DB::table('shipments')
                ->where('tenant_id', $tenantId)->where('store_id', $storeId)
                ->whereIn('status', [ShipmentStatus::Pending->value, ShipmentStatus::Ready->value])
                ->count(),
            'shipped_not_delivered' => DB::table('shipments')
                ->where('tenant_id', $tenantId)->where('store_id', $storeId)
                ->where('status', ShipmentStatus::Shipped->value)
                ->count(),
        ];
    }
}
