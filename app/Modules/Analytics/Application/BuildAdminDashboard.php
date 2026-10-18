<?php

namespace App\Modules\Analytics\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Analytics\Domain\Data\AdminDashboardData;
use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Stores\Domain\Models\Store;

final readonly class BuildAdminDashboard
{
    public function __construct(
        private PermissionChecker $permissions,
        private SalesDashboardMetrics $sales,
        private PaymentDashboardMetrics $payments,
        private OperationsDashboardMetrics $operations,
        private RecentOrdersDashboardMetrics $recentOrders,
        private InventoryDashboardMetrics $inventory,
        private ProcurementDashboardMetrics $procurement,
        private ReturnsDashboardMetrics $returns,
        private FiscalDashboardMetrics $fiscal,
        private ProfitabilityDashboardMetrics $profitability,
    ) {}

    public function build(User $user, Store $store, DashboardDateRange $range): AdminDashboardData
    {
        $tenantId = $store->tenant_id;
        $storeId = (string) $store->getKey();
        $currency = $store->currency;
        $previous = $range->previous();
        $allowed = array_fill_keys($this->permissions->permissions($user), true);
        $can = fn (PermissionKey $permission): bool => isset($allowed[$permission->value]);
        $sections = [];
        $attention = [];
        $metrics = [];

        if ($can(PermissionKey::OrdersView)) {
            $sections['sales'] = $this->sales->build($tenantId, $storeId, $currency, $range);
            $previousSales = $this->sales->summary($tenantId, $storeId, $currency, $previous);
            $metrics['gross_sales'] = MetricComparison::money($sections['sales']['gross_sales'], $previousSales['gross_sales']);
            $metrics['orders'] = MetricComparison::count($sections['sales']['orders'], $previousSales['orders']);
            $metrics['average_order_value'] = MetricComparison::money(
                $sections['sales']['average_order_value'], $previousSales['average_order_value'],
            );
            $sections['recent_orders'] = $this->recentOrders->build(
                $tenantId, $storeId, $currency, $range,
                $can(PermissionKey::PaymentsView), $can(PermissionKey::FulfillmentsView),
            );
        }

        if ($can(PermissionKey::InventoryCostView)) {
            $sections['profitability'] = $this->profitability->build($tenantId, $storeId, $currency, $range);
        }

        if ($can(PermissionKey::PaymentsView)) {
            $includeRefunds = $can(PermissionKey::RefundsView);
            $sections['payments'] = $this->payments->build($tenantId, $storeId, $currency, $range, $includeRefunds);
            $previousPayments = $this->payments->summary($tenantId, $storeId, $currency, $previous, $includeRefunds);
            $metrics['captured_amount'] = MetricComparison::money(
                $sections['payments']['captured_amount'], $previousPayments['captured_amount'],
            );
            $metrics['net_paid'] = MetricComparison::money($sections['payments']['net_paid'], $previousPayments['net_paid']);
            if ($includeRefunds) {
                $metrics['refunded_amount'] = MetricComparison::money(
                    $sections['payments']['refunded_amount'], $previousPayments['refunded_amount'],
                );
            }
            $attention[] = $this->attention('unpaid-orders', 'Unpaid orders', $sections['payments']['unpaid_orders'], '/admin/orders');
        }

        if ($can(PermissionKey::FulfillmentsView)) {
            $sections['fulfillment'] = [
                'orders_by_status' => $this->operations->fulfillment($tenantId, $storeId, $currency, $range),
                'fulfillments_pending' => $this->operations->fulfillmentPending($tenantId, $storeId),
            ];
            if ($can(PermissionKey::ShipmentsView)) {
                $sections['fulfillment'] = [
                    ...$sections['fulfillment'],
                    ...$this->operations->shipmentAttention($tenantId, $storeId),
                ];
            }
            $attention[] = $this->attention('unfulfilled', 'Fulfillments pending', $sections['fulfillment']['fulfillments_pending'], '/admin/fulfillments');
        }

        if ($can(PermissionKey::InventoryView)) {
            $sections['inventory'] = $this->inventory->build($tenantId);
            $attention[] = $this->attention('low-stock', 'Low stock', $sections['inventory']['low_stock_variants'], '/admin/inventory', 'tenant');
        }

        if ($can(PermissionKey::ProcurementView)) {
            $sections['procurement'] = $this->procurement->build($tenantId);
            $attention[] = $this->attention('purchase-orders', 'Purchase orders to receive', $sections['procurement']['to_receive'], '/admin/purchase-orders', 'tenant');
        }

        if ($can(PermissionKey::ReturnsView)) {
            $sections['returns'] = $this->returns->build($tenantId, $storeId, $can(PermissionKey::ExchangesView));
            $attention[] = $this->attention('returns', 'Returns awaiting inspection', $sections['returns']['awaiting_inspection'], '/admin/returns');
        }

        if ($can(PermissionKey::FiscalDocumentsView)) {
            $sections['fiscal'] = $this->fiscal->build($tenantId, $storeId, $range);
            $attention[] = $this->attention('fiscal', 'Fiscal documents', $sections['fiscal']['attention'], '/admin/fiscal-documents');
        }

        $comparison = [
            'previous_range' => $previous->toArray(),
            'same_length' => true,
            'metrics' => $metrics,
        ];

        return new AdminDashboardData($range, $currency, $sections, $attention, $comparison);
    }

    /** @return array<string, mixed> */
    private function attention(string $key, string $label, int $count, string $href, string $scope = 'store'): array
    {
        return compact('key', 'label', 'count', 'href', 'scope');
    }
}
