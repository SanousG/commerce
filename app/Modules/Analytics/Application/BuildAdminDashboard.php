<?php

namespace App\Modules\Analytics\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Analytics\Domain\Data\AdminDashboardData;
use App\Modules\Analytics\Domain\Data\DashboardDateRange;
use App\Modules\Analytics\Domain\Enums\DashboardRange;
use App\Modules\Stores\Domain\Models\Store;

final readonly class BuildAdminDashboard
{
    public function __construct(
        private PermissionChecker $permissions,
        private SalesDashboardMetrics $sales,
        private PaymentDashboardMetrics $payments,
        private OperationsDashboardMetrics $operations,
        private InventoryDashboardMetrics $inventory,
        private ProcurementDashboardMetrics $procurement,
        private ReturnsDashboardMetrics $returns,
        private FiscalDashboardMetrics $fiscal,
        private ProfitabilityDashboardMetrics $profitability,
    ) {}

    public function build(User $user, Store $store, DashboardRange $rangeKey): AdminDashboardData
    {
        $tenantId = $store->tenant_id;
        $storeId = (string) $store->getKey();
        $currency = $store->currency;
        $range = DashboardDateRange::make($rangeKey, $store->timezone);
        $allowed = array_fill_keys($this->permissions->permissions($user), true);
        $can = fn (PermissionKey $permission): bool => isset($allowed[$permission->value]);
        $sections = [];
        $attention = [];

        if ($can(PermissionKey::OrdersView)) {
            $sections['sales'] = $this->sales->build($tenantId, $storeId, $currency, $range);
        }

        if ($can(PermissionKey::InventoryCostView)) {
            $sections['profitability'] = $this->profitability->build($tenantId, $storeId, $currency, $range);
        }

        if ($can(PermissionKey::PaymentsView)) {
            $sections['payments'] = $this->payments->build(
                $tenantId, $storeId, $currency, $range, $can(PermissionKey::RefundsView),
            );
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

        return new AdminDashboardData($range, $currency, $sections, $attention);
    }

    /** @return array<string, mixed> */
    private function attention(string $key, string $label, int $count, string $href, string $scope = 'store'): array
    {
        return compact('key', 'label', 'count', 'href', 'scope');
    }
}
