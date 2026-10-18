<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

final class ProfitabilityDashboardMetrics
{
    /** @return array<string, int|string|null> */
    public function build(string $tenantId, string $storeId, string $currency, DashboardDateRange $range): array
    {
        $row = DB::table('fulfillment_items as fi')
            ->join('fulfillments as f', 'f.id', '=', 'fi.fulfillment_id')
            ->join('order_items as oi', 'oi.id', '=', 'fi.order_item_id')
            ->join('orders as o', 'o.id', '=', 'fi.order_id')
            ->where('fi.tenant_id', $tenantId)
            ->where('fi.store_id', $storeId)
            ->where('f.status', FulfillmentStatus::Fulfilled->value)
            ->where('f.fulfilled_at', '>=', $range->utcStart)
            ->where('f.fulfilled_at', '<', $range->utcEnd)
            ->where('o.currency', $currency)
            ->selectRaw('COALESCE(SUM(fi.quantity), 0) AS fulfilled_quantity')
            ->selectRaw('COALESCE(SUM(CASE WHEN fi.unit_cost_snapshot IS NOT NULL AND fi.cost_currency = ? THEN fi.quantity ELSE 0 END), 0) AS covered_quantity', [$currency])
            ->selectRaw('COALESCE(SUM(CASE WHEN fi.unit_cost_snapshot IS NOT NULL AND fi.cost_currency = ? THEN fi.cogs_amount ELSE 0 END), 0) AS cogs', [$currency])
            ->selectRaw('COALESCE(SUM(ROUND(((oi.line_total - COALESCE(oi.tax_amount, 0)) * fi.quantity / oi.quantity), 4)), 0) AS realized_revenue')
            ->first();

        $fulfilled = (int) $row->fulfilled_quantity;
        $covered = (int) $row->covered_quantity;
        $revenue = BigDecimal::of($row->realized_revenue)->toScale(4, RoundingMode::HalfUp);
        $cogs = BigDecimal::of($row->cogs)->toScale(4, RoundingMode::HalfUp);
        $complete = $fulfilled === $covered;
        $profit = $complete ? $revenue->minus($cogs)->toScale(4) : null;

        return [
            'fulfilled_revenue_ex_tax' => (string) $revenue,
            'cogs' => (string) $cogs,
            'gross_profit' => $profit === null ? null : (string) $profit,
            'gross_margin_percent' => $profit === null || $revenue->isZero() ? null : (string) $profit
                ->multipliedBy(100)->dividedBy($revenue, 2, RoundingMode::HalfUp),
            'fulfilled_quantity' => $fulfilled,
            'covered_quantity' => $covered,
            'cost_coverage_percent' => $fulfilled === 0 ? '100.00' : (string) BigDecimal::of($covered)
                ->multipliedBy(100)->dividedBy($fulfilled, 2, RoundingMode::HalfUp),
            'status' => $complete ? 'COMPLETE' : 'INCOMPLETE',
        ];
    }
}
