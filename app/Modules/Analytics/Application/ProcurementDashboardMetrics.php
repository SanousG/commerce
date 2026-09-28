<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use Illuminate\Support\Facades\DB;

final class ProcurementDashboardMetrics
{
    /** @return array<string, mixed> */
    public function build(string $tenantId): array
    {
        $statusCounts = DB::table('purchase_orders')
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $ordered = DB::table('purchase_order_items')
            ->where('tenant_id', $tenantId)
            ->groupBy('purchase_order_id')
            ->selectRaw('purchase_order_id, SUM(quantity_ordered) AS ordered_quantity');

        $received = DB::table('purchase_receipt_items')
            ->join('purchase_receipts', function ($join) {
                $join->on('purchase_receipts.id', '=', 'purchase_receipt_items.purchase_receipt_id')
                    ->on('purchase_receipts.tenant_id', '=', 'purchase_receipt_items.tenant_id');
            })
            ->where('purchase_receipt_items.tenant_id', $tenantId)
            ->where('purchase_receipts.status', PurchaseReceiptStatus::Posted->value)
            ->groupBy('purchase_receipt_items.purchase_order_id')
            ->selectRaw('purchase_receipt_items.purchase_order_id, SUM(purchase_receipt_items.quantity) AS received_quantity');

        $open = DB::table('purchase_orders')
            ->join('suppliers', function ($join) {
                $join->on('suppliers.id', '=', 'purchase_orders.supplier_id')
                    ->on('suppliers.tenant_id', '=', 'purchase_orders.tenant_id');
            })
            ->leftJoinSub($ordered, 'ordered', 'ordered.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoinSub($received, 'received', 'received.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.tenant_id', $tenantId)
            ->whereIn('purchase_orders.status', [
                PurchaseOrderStatus::Ordered->value,
                PurchaseOrderStatus::PartiallyReceived->value,
            ])
            ->select([
                'purchase_orders.id', 'purchase_orders.number', 'purchase_orders.status',
                'purchase_orders.expected_at', 'suppliers.name as supplier',
            ])
            ->selectRaw('GREATEST(COALESCE(ordered.ordered_quantity, 0) - COALESCE(received.received_quantity, 0), 0) AS remaining_quantity');

        return [
            'by_status' => collect(PurchaseOrderStatus::cases())->mapWithKeys(
                fn (PurchaseOrderStatus $status) => [$status->value => (int) ($statusCounts[$status->value] ?? 0)]
            )->all(),
            'to_receive' => DB::query()->fromSub(clone $open, 'open_pos')->count(),
            'overdue' => DB::query()->fromSub(clone $open, 'open_pos')
                ->whereNotNull('expected_at')->where('expected_at', '<', now())->count(),
            'incoming_units' => (int) DB::query()->fromSub(clone $open, 'open_pos')->sum('remaining_quantity'),
            'purchase_orders' => $open
                ->orderByRaw('purchase_orders.expected_at ASC NULLS LAST')
                ->orderBy('purchase_orders.number')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'number' => $row->number,
                    'supplier' => $row->supplier,
                    'expected_at' => $row->expected_at,
                    'remaining_quantity' => (int) $row->remaining_quantity,
                    'status' => $row->status,
                ])->all(),
        ];
    }
}
