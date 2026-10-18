<?php

namespace App\Modules\Costing\Application;

use App\Modules\Fulfillment\Domain\Enums\FulfillmentStatus;
use App\Modules\Orders\Domain\Models\Order;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

final class OrderProfitability
{
    /** @return array<string, mixed> */
    public function build(Order $order): array
    {
        $rows = DB::table('order_items as oi')
            ->leftJoin('fulfillment_items as fi', 'fi.order_item_id', '=', 'oi.id')
            ->leftJoin('fulfillments as f', function ($join): void {
                $join->on('f.id', '=', 'fi.fulfillment_id')->where('f.status', FulfillmentStatus::Fulfilled->value);
            })
            ->where('oi.tenant_id', $order->tenant_id)
            ->where('oi.store_id', $order->store_id)
            ->where('oi.order_id', $order->getKey())
            ->groupBy('oi.id', 'oi.product_name', 'oi.variant_name', 'oi.sku', 'oi.quantity', 'oi.line_total', 'oi.tax_amount')
            ->orderBy('oi.id')
            ->get([
                'oi.id', 'oi.product_name', 'oi.variant_name', 'oi.sku', 'oi.quantity', 'oi.line_total', 'oi.tax_amount',
                DB::raw('COALESCE(SUM(CASE WHEN f.id IS NOT NULL THEN fi.quantity ELSE 0 END), 0) AS fulfilled_quantity'),
                DB::raw('COALESCE(SUM(CASE WHEN f.id IS NOT NULL AND fi.unit_cost_snapshot IS NOT NULL AND fi.cost_currency = '.DB::getPdo()->quote($order->currency).' THEN fi.quantity ELSE 0 END), 0) AS covered_quantity'),
                DB::raw('COALESCE(SUM(CASE WHEN f.id IS NOT NULL AND fi.unit_cost_snapshot IS NOT NULL AND fi.cost_currency = '.DB::getPdo()->quote($order->currency).' THEN fi.cogs_amount ELSE 0 END), 0) AS cogs'),
            ]);

        $items = $rows->map(function ($row): array {
            $orderedRevenue = BigDecimal::of($row->line_total)->minus($row->tax_amount ?? '0');
            $fulfilledQuantity = (int) $row->fulfilled_quantity;
            $realizedRevenue = $fulfilledQuantity === (int) $row->quantity
                ? $orderedRevenue->toScale(4, RoundingMode::HalfUp)
                : $orderedRevenue->multipliedBy($fulfilledQuantity)
                    ->dividedBy((int) $row->quantity, 4, RoundingMode::HalfUp);
            $cogs = BigDecimal::of($row->cogs)->toScale(4, RoundingMode::HalfUp);
            $complete = $fulfilledQuantity === (int) $row->covered_quantity;
            $profit = $complete ? $realizedRevenue->minus($cogs)->toScale(4) : null;

            return [
                'order_item_id' => $row->id,
                'product_name' => $row->product_name,
                'variant_name' => $row->variant_name,
                'sku' => $row->sku,
                'ordered_quantity' => (int) $row->quantity,
                'fulfilled_quantity' => $fulfilledQuantity,
                'covered_quantity' => (int) $row->covered_quantity,
                'ordered_revenue_ex_tax' => (string) $orderedRevenue->toScale(4, RoundingMode::HalfUp),
                'fulfilled_revenue_ex_tax' => (string) $realizedRevenue,
                'cogs' => (string) $cogs,
                'gross_profit' => $profit === null ? null : (string) $profit,
                'gross_margin_percent' => $profit === null ? null : $this->margin($profit, $realizedRevenue),
                'complete' => $complete,
            ];
        });

        $fulfilled = (int) $items->sum('fulfilled_quantity');
        $covered = (int) $items->sum('covered_quantity');
        $orderedRevenue = $items->reduce(fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['ordered_revenue_ex_tax']), BigDecimal::zero());
        $realizedRevenue = $items->reduce(fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['fulfilled_revenue_ex_tax']), BigDecimal::zero());
        $cogs = $items->reduce(fn (BigDecimal $sum, array $item): BigDecimal => $sum->plus($item['cogs']), BigDecimal::zero());
        $complete = $fulfilled === $covered;
        $profit = $complete ? $realizedRevenue->minus($cogs)->toScale(4) : null;

        return [
            'currency' => $order->currency,
            'ordered_revenue_ex_tax' => (string) $orderedRevenue->toScale(4, RoundingMode::HalfUp),
            'fulfilled_revenue_ex_tax' => (string) $realizedRevenue->toScale(4, RoundingMode::HalfUp),
            'cogs' => (string) $cogs->toScale(4, RoundingMode::HalfUp),
            'gross_profit' => $profit === null ? null : (string) $profit,
            'gross_margin_percent' => $profit === null ? null : $this->margin($profit, $realizedRevenue),
            'fulfilled_quantity' => $fulfilled,
            'covered_quantity' => $covered,
            'cost_coverage_percent' => $fulfilled === 0 ? '100.00' : (string) BigDecimal::of($covered)
                ->multipliedBy(100)->dividedBy($fulfilled, 2, RoundingMode::HalfUp),
            'status' => $complete ? 'COMPLETE' : 'INCOMPLETE',
            'items' => $items->values(),
        ];
    }

    private function margin(BigDecimal $profit, BigDecimal $revenue): ?string
    {
        if ($revenue->isZero()) {
            return null;
        }

        return (string) $profit->multipliedBy(100)->dividedBy($revenue, 2, RoundingMode::HalfUp);
    }
}
