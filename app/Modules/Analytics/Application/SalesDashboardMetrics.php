<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class SalesDashboardMetrics
{
    /**
     * Scalar sales KPIs only. Used for the period-over-period comparison so the
     * previous window never recomputes the trend or the top product list.
     *
     * @return array{orders: int, gross_sales: string, average_order_value: string}
     */
    public function summary(string $tenantId, string $storeId, string $currency, DashboardDateRange $range): array
    {
        $totals = $this->eligibleOrders($tenantId, $storeId, $currency, $range)
            ->selectRaw('COUNT(*) AS order_count, COALESCE(SUM(grand_total), 0) AS gross_sales')
            ->first();

        $orderCount = (int) $totals->order_count;
        $grossSales = BigDecimal::of($totals->gross_sales)->toScale(4);
        $average = $orderCount === 0
            ? BigDecimal::zero()->toScale(4)
            : $grossSales->dividedBy($orderCount, 4, RoundingMode::HalfUp);

        return [
            'orders' => $orderCount,
            'gross_sales' => (string) $grossSales,
            'average_order_value' => (string) $average,
        ];
    }

    /** @return array<string, mixed> */
    public function build(string $tenantId, string $storeId, string $currency, DashboardDateRange $range): array
    {
        $summary = $this->summary($tenantId, $storeId, $currency, $range);

        $statusCounts = $this->scopedOrders($tenantId, $storeId, $currency, $range)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $trendRows = $this->eligibleOrders($tenantId, $storeId, $currency, $range)
            ->selectRaw("TO_CHAR(timezone(?, placed_at), 'YYYY-MM-DD') AS local_date", [$range->localStart->timezoneName])
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(grand_total), 0) AS gross_sales')
            ->groupByRaw('1')
            ->get()
            ->keyBy('local_date');

        $trend = [];
        for ($day = $range->localStart; $day->lessThan($range->localEnd); $day = $day->addDay()) {
            $date = $day->toDateString();
            $row = $trendRows->get($date);
            $trend[] = [
                'date' => $date,
                'gross_sales' => DashboardMoney::value($row?->gross_sales),
                'orders' => (int) ($row?->orders ?? 0),
            ];
        }

        $topProducts = DB::table('order_items')
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'order_items.order_id')
                    ->on('orders.tenant_id', '=', 'order_items.tenant_id')
                    ->on('orders.store_id', '=', 'order_items.store_id');
            })
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.store_id', $storeId)
            ->where('orders.currency', $currency)
            ->where('orders.status', '<>', OrderStatus::Cancelled->value)
            ->where('orders.placed_at', '>=', $range->utcStart)
            ->where('orders.placed_at', '<', $range->utcEnd)
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc(DB::raw('SUM(order_items.quantity)'))
            ->orderBy('order_items.product_name')
            ->limit(10)
            ->get([
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) AS units'),
                DB::raw('COALESCE(SUM(order_items.line_total), 0) AS revenue'),
            ])
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product' => $row->product_name,
                'units' => (int) $row->units,
                'revenue' => DashboardMoney::value($row->revenue),
            ])->all();

        return [
            'gross_sales' => $summary['gross_sales'],
            'orders' => $summary['orders'],
            'average_order_value' => $summary['average_order_value'],
            'orders_by_status' => collect(OrderStatus::cases())->mapWithKeys(
                fn (OrderStatus $status) => [$status->value => (int) ($statusCounts[$status->value] ?? 0)]
            )->all(),
            'trend' => $trend,
            'top_products' => $topProducts,
            'excluded_currency_orders' => DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('currency', '<>', $currency)
                ->where('placed_at', '>=', $range->utcStart)
                ->where('placed_at', '<', $range->utcEnd)
                ->count(),
        ];
    }

    private function scopedOrders(
        string $tenantId,
        string $storeId,
        string $currency,
        DashboardDateRange $range,
    ): Builder {
        return DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('currency', $currency)
            ->where('placed_at', '>=', $range->utcStart)
            ->where('placed_at', '<', $range->utcEnd);
    }

    private function eligibleOrders(
        string $tenantId,
        string $storeId,
        string $currency,
        DashboardDateRange $range,
    ): Builder {
        return $this->scopedOrders($tenantId, $storeId, $currency, $range)
            ->where('status', '<>', OrderStatus::Cancelled->value);
    }
}
