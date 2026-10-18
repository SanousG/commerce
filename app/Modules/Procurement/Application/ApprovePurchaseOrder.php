<?php

namespace App\Modules\Procurement\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovePurchaseOrder
{
    public function __construct(
        private readonly PurchaseOrderCalculator $calculator,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(Tenant $tenant, PurchaseOrder $order, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($tenant, $order, $actor): PurchaseOrder {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if ($order->status !== PurchaseOrderStatus::Draft
                || $order->supplier()->firstOrFail()->status !== SupplierStatus::Active) {
                throw ValidationException::withMessages(['purchase_order' => 'Only a draft order for an active supplier can be approved.']);
            }
            $items = $order->items()->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'A purchase order requires at least one item.']);
            }
            $products = SupplierProduct::query()->where('tenant_id', $tenant->getKey())
                ->whereIn('id', $items->pluck('supplier_product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                $product = $products->get($item->supplier_product_id);
                if ($product === null || $product->status !== SupplierProductStatus::Active
                    || $product->supplier_id !== $order->supplier_id || $item->currency !== $order->currency
                    || $item->quantity_ordered < $product->minimum_order_quantity
                    || ($product->order_multiple !== null && $item->quantity_ordered % $product->order_multiple !== 0)) {
                    throw ValidationException::withMessages(['items' => 'Every item must remain active and satisfy supplier, currency, MOQ, and multiple rules.']);
                }
            }
            $order = $this->calculator->handle($order);
            $order->update(['status' => PurchaseOrderStatus::Approved, 'approved_by_user_id' => $actor->getKey()]);
            $this->audit->record(
                tenant: $tenant, actorType: AuditActorType::User, action: AuditAction::PurchaseOrderApproved,
                subjectType: AuditSubjectType::PurchaseOrder, subjectId: $order->getKey(),
                metadata: ['quantity' => (int) $items->sum('quantity_ordered'), 'grand_total' => $order->grand_total, 'currency' => $order->currency],
                actor: $actor, subjectLabel: $order->number,
            );

            return $order->refresh()->load('items');
        });
    }
}
