<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Fiscal\Domain\Enums\FiscalSubmissionStatus;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Notifications\Domain\Enums\NotificationSeverity;
use App\Modules\Notifications\Domain\Enums\NotificationType;
use App\Modules\Payments\Domain\Enums\PaymentStatus;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Returns\Domain\Enums\ReturnStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReconcileOperationalNotifications
{
    public function __construct(
        private readonly NotificationRecipientResolver $recipients,
        private readonly NotificationManager $notifications,
    ) {}

    /** @return array{conditions:int, deliveries:int, resolved:int} */
    public function reconcile(string $tenantId): array
    {
        $totals = ['conditions' => 0, 'deliveries' => 0, 'resolved' => 0];

        $stockRecipients = $this->recipients->userIds($tenantId, [PermissionKey::InventoryView]);
        $threshold = max(0, (int) config('commerce.inventory.low_stock_threshold', 5));
        $stock = DB::query()->fromSub($this->availabilityQuery($tenantId), 'stock')
            ->where('available', '<=', $threshold)->get();
        $this->sync($tenantId, null, 'condition:stock:', $stock, $stockRecipients, $totals,
            fn ($row): string => 'stock:'.$row->variant_id,
            fn ($row): array => [
                (int) $row->available <= 0 ? NotificationType::OutOfStock : NotificationType::LowStock,
                (int) $row->available <= 0 ? NotificationSeverity::Critical : NotificationSeverity::Warning,
                (int) $row->available <= 0 ? 'Variant out of stock' : 'Variant low on stock',
                "{$row->product_name} ({$row->sku}) has {$row->available} available.",
                '/admin/inventory/variants/'.$row->variant_id,
                ['variant_id' => $row->variant_id, 'available' => (int) $row->available, 'threshold' => $threshold],
            ]);

        $purchaseOrders = DB::table('purchase_orders')->where('tenant_id', $tenantId)
            ->whereIn('status', [PurchaseOrderStatus::Ordered->value, PurchaseOrderStatus::PartiallyReceived->value])
            ->whereNotNull('expected_at')->where('expected_at', '<', now())
            ->get(['id', 'number', 'expected_at']);
        $this->sync($tenantId, null, 'condition:purchase-order:', $purchaseOrders,
            $this->recipients->userIds($tenantId, [PermissionKey::ProcurementReceive]), $totals,
            fn ($row): string => 'purchase-order:'.$row->id,
            fn ($row): array => [NotificationType::PurchaseOrderToReceive, NotificationSeverity::Warning,
                'Purchase order overdue', "Purchase order {$row->number} is overdue for receipt.",
                '/admin/purchase-orders/'.$row->id, ['purchase_order_id' => $row->id]]);

        $returns = DB::table('returns')->where('tenant_id', $tenantId)
            ->where('status', ReturnStatus::Received->value)->get(['id', 'store_id']);
        $this->syncByStore($tenantId, 'condition:return:', $returns,
            $this->recipients->userIds($tenantId, [PermissionKey::ReturnsInspect]), $totals,
            fn ($row): string => 'return:'.$row->id,
            fn ($row): array => [NotificationType::ReturnAwaitingInspection, NotificationSeverity::Warning,
                'Return awaiting inspection', 'A received return is ready for inspection.',
                '/admin/returns/'.$row->id, ['return_id' => $row->id]]);

        $refunds = DB::table('refunds')->where('tenant_id', $tenantId)
            ->where('status', RefundStatus::Pending->value)->get(['id', 'store_id']);
        $this->syncByStore($tenantId, 'condition:refund:', $refunds,
            $this->recipients->userIds($tenantId, [PermissionKey::RefundsManage]), $totals,
            fn ($row): string => 'refund:'.$row->id,
            fn ($row): array => [NotificationType::RefundPending, NotificationSeverity::Warning,
                'Refund pending', 'A refund requires completion.', '/admin/refunds/'.$row->id, ['refund_id' => $row->id]]);

        $payments = DB::table('payments')->where('tenant_id', $tenantId)
            ->where('status', PaymentStatus::Pending->value)->get(['id', 'store_id']);
        $this->syncByStore($tenantId, 'condition:payment:', $payments,
            $this->recipients->userIds($tenantId, [PermissionKey::PaymentsManage]), $totals,
            fn ($row): string => 'payment:'.$row->id,
            fn ($row): array => [NotificationType::PaymentPending, NotificationSeverity::Info,
                'Payment pending', 'A payment is awaiting processing.', '/admin/payments/'.$row->id, ['payment_id' => $row->id]]);

        $fiscal = DB::table('fiscal_submissions as submission')
            ->join('fiscal_documents as document', function ($join) {
                $join->on('document.id', '=', 'submission.fiscal_document_id')
                    ->on('document.tenant_id', '=', 'submission.tenant_id');
            })
            ->where('submission.tenant_id', $tenantId)
            ->whereIn('submission.status', [FiscalSubmissionStatus::Rejected->value, FiscalSubmissionStatus::Unknown->value])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('fiscal_submissions as later')
                ->whereColumn('later.fiscal_document_id', 'submission.fiscal_document_id')
                ->whereColumn('later.attempt_number', '>', 'submission.attempt_number'))
            ->get(['document.id', 'document.store_id', 'document.full_number', 'submission.status']);
        $this->syncByStore($tenantId, 'condition:fiscal:', $fiscal,
            $this->recipients->userIds($tenantId, [PermissionKey::FiscalDocumentsManage, PermissionKey::FiscalDocumentsIssue]), $totals,
            fn ($row): string => 'fiscal:'.$row->id,
            fn ($row): array => [
                $row->status === FiscalSubmissionStatus::Rejected->value ? NotificationType::FiscalRejected : NotificationType::FiscalUnknown,
                $row->status === FiscalSubmissionStatus::Rejected->value ? NotificationSeverity::Critical : NotificationSeverity::Warning,
                $row->status === FiscalSubmissionStatus::Rejected->value ? 'Fiscal document rejected' : 'Fiscal document status unknown',
                "Fiscal document {$row->full_number} requires attention.", '/admin/fiscal-documents/'.$row->id,
                ['fiscal_document_id' => $row->id],
            ]);

        return $totals;
    }

    private function availabilityQuery(string $tenantId)
    {
        return DB::table('product_variants')
            ->join('products', fn ($join) => $join->on('products.id', '=', 'product_variants.product_id')
                ->on('products.tenant_id', '=', 'product_variants.tenant_id'))
            ->leftJoin('inventory_levels', function ($join) {
                $join->on('inventory_levels.product_variant_id', '=', 'product_variants.id')
                    ->on('inventory_levels.tenant_id', '=', 'product_variants.tenant_id')
                    ->whereExists(fn ($query) => $query->selectRaw('1')->from('inventory_locations')
                        ->whereColumn('inventory_locations.id', 'inventory_levels.inventory_location_id')
                        ->whereColumn('inventory_locations.tenant_id', 'inventory_levels.tenant_id')
                        ->where('inventory_locations.status', InventoryLocationStatus::Active->value));
            })
            ->where('product_variants.tenant_id', $tenantId)
            ->where('product_variants.status', ProductVariantStatus::Active->value)
            ->where('products.status', ProductStatus::Active->value)
            ->groupBy('product_variants.id', 'products.name', 'product_variants.sku')
            ->select(['product_variants.id as variant_id', 'products.name as product_name', 'product_variants.sku'])
            ->selectRaw('COALESCE(SUM(inventory_levels.on_hand - inventory_levels.reserved), 0) AS available');
    }

    private function syncByStore(string $tenantId, string $prefix, Collection $rows, Collection $recipients, array &$totals, callable $key, callable $content): void
    {
        $storeIds = DB::table('stores')->where('tenant_id', $tenantId)->pluck('id');
        foreach ($storeIds as $storeId) {
            $this->sync($tenantId, (string) $storeId, $prefix, $rows->where('store_id', $storeId)->values(), $recipients, $totals, $key, $content);
        }
    }

    private function sync(string $tenantId, ?string $storeId, string $prefix, Collection $rows, Collection $recipients, array &$totals, callable $key, callable $content): void
    {
        $keys = $rows->map(fn ($row): string => 'condition:'.$key($row))->all();
        $totals['resolved'] += $this->notifications->resolvePrefix(
            $tenantId,
            $storeId,
            $prefix,
            $keys,
            $recipients->map(fn ($id): int => (int) $id)->all(),
        );

        foreach ($rows as $row) {
            [$type, $severity, $title, $message, $url, $metadata] = $content($row);
            $totals['conditions']++;
            $totals['deliveries'] += $this->notifications->deliverCondition(
                $tenantId, $storeId, $recipients, $key($row), $type, $severity, $title, $message, $url, $metadata
            )->count();
        }
    }
}
