<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionStatus;
use App\Modules\Payments\Domain\Enums\PaymentTransactionType;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use Illuminate\Support\Facades\DB;

final class PaymentDashboardMetrics
{
    /**
     * Scalar payment KPIs for the current and previous windows.
     *
     * @return array<string, string>
     */
    public function summary(
        string $tenantId,
        string $storeId,
        string $currency,
        DashboardDateRange $range,
        bool $includeRefunds,
    ): array {
        $ledger = DB::table('payment_transactions')
            ->join('payments', function ($join) {
                $join->on('payments.id', '=', 'payment_transactions.payment_id')
                    ->on('payments.tenant_id', '=', 'payment_transactions.tenant_id');
            })
            ->where('payment_transactions.tenant_id', $tenantId)
            ->where('payments.tenant_id', $tenantId)
            ->where('payments.store_id', $storeId)
            ->where('payments.currency', $currency)
            ->where('payment_transactions.currency', $currency)
            ->where('payment_transactions.status', PaymentTransactionStatus::Succeeded->value)
            ->whereIn('payment_transactions.type', [
                PaymentTransactionType::Capture->value,
                PaymentTransactionType::Refund->value,
            ])
            ->groupBy('payments.order_id')
            ->select('payments.order_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_transactions.type = 'CAPTURE' THEN payment_transactions.amount ELSE 0 END), 0) AS captured")
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_transactions.type = 'REFUND' THEN payment_transactions.amount ELSE 0 END), 0) AS refunded");

        $balances = DB::table('orders')
            ->leftJoinSub($ledger, 'ledger', 'ledger.order_id', '=', 'orders.id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.store_id', $storeId)
            ->where('orders.currency', $currency)
            ->where('orders.status', '<>', OrderStatus::Cancelled->value)
            ->where('orders.placed_at', '>=', $range->utcStart)
            ->where('orders.placed_at', '<', $range->utcEnd)
            ->selectRaw('COALESCE(SUM(COALESCE(ledger.captured, 0)), 0) AS captured_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(ledger.captured, 0) - COALESCE(ledger.refunded, 0)), 0) AS net_paid')
            ->selectRaw('COALESCE(SUM(GREATEST(orders.grand_total - (COALESCE(ledger.captured, 0) - COALESCE(ledger.refunded, 0)), 0)), 0) AS pending_amount')
            ->first();

        $data = [
            'captured_amount' => DashboardMoney::value($balances->captured_amount),
            'net_paid' => DashboardMoney::value($balances->net_paid),
            'pending_amount' => DashboardMoney::value($balances->pending_amount),
        ];

        if ($includeRefunds) {
            $data['refunded_amount'] = DashboardMoney::value(DB::table('refunds')
                ->where('tenant_id', $tenantId)->where('store_id', $storeId)->where('currency', $currency)
                ->where('status', RefundStatus::Completed->value)
                ->where('completed_at', '>=', $range->utcStart)->where('completed_at', '<', $range->utcEnd)
                ->sum('amount'));
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function build(
        string $tenantId,
        string $storeId,
        string $currency,
        DashboardDateRange $range,
        bool $includeRefunds,
    ): array {
        $paymentStatuses = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('currency', $currency)
            ->where('placed_at', '>=', $range->utcStart)
            ->where('placed_at', '<', $range->utcEnd)
            ->selectRaw('payment_status, COUNT(*) AS aggregate')
            ->groupBy('payment_status')
            ->pluck('aggregate', 'payment_status');

        $data = $this->summary($tenantId, $storeId, $currency, $range, $includeRefunds) + [
            'unpaid_orders' => DB::table('orders')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('currency', $currency)
                ->where('status', '<>', OrderStatus::Cancelled->value)
                ->whereIn('payment_status', [
                    OrderPaymentStatus::Unpaid->value,
                    OrderPaymentStatus::PartiallyPaid->value,
                ])->count(),
            'orders_by_payment_status' => collect(OrderPaymentStatus::cases())->mapWithKeys(
                fn (OrderPaymentStatus $status) => [$status->value => (int) ($paymentStatuses[$status->value] ?? 0)]
            )->all(),
            'currency_warning_transactions' => DB::table('payment_transactions')
                ->join('payments', 'payments.id', '=', 'payment_transactions.payment_id')
                ->where('payment_transactions.tenant_id', $tenantId)
                ->where('payments.tenant_id', $tenantId)
                ->where('payments.store_id', $storeId)
                ->where('payment_transactions.status', PaymentTransactionStatus::Succeeded->value)
                ->where(fn ($query) => $query
                    ->where('payments.currency', '<>', $currency)
                    ->orWhere('payment_transactions.currency', '<>', $currency))
                ->count(),
        ];

        if ($includeRefunds) {
            $refundStatuses = DB::table('refunds')
                ->where('tenant_id', $tenantId)->where('store_id', $storeId)->where('currency', $currency)
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $data['pending_refunds'] = (int) ($refundStatuses[RefundStatus::Pending->value] ?? 0);
            $data['refunds_by_status'] = collect(RefundStatus::cases())->mapWithKeys(
                fn (RefundStatus $status) => [$status->value => (int) ($refundStatuses[$status->value] ?? 0)]
            )->all();
        }

        return $data;
    }
}
