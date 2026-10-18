<?php

namespace App\Modules\Procurement\Application;

use App\Models\User;
use App\Modules\Audit\Application\AuditRecorder;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Enums\AuditActorType;
use App\Modules\Audit\Domain\Enums\AuditSubjectType;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkPurchaseOrderOrdered
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(Tenant $tenant, PurchaseOrder $order, ?User $actor = null): PurchaseOrder
    {
        return DB::transaction(function () use ($tenant, $order, $actor): PurchaseOrder {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if ($order->status !== PurchaseOrderStatus::Approved) {
                throw ValidationException::withMessages(['purchase_order' => 'Only an approved purchase order can be marked ordered.']);
            }
            $order->update(['status' => PurchaseOrderStatus::Ordered, 'ordered_at' => now()]);
            $this->audit->record(
                tenant: $tenant, actorType: $actor === null ? AuditActorType::System : AuditActorType::User,
                action: AuditAction::PurchaseOrderOrdered, subjectType: AuditSubjectType::PurchaseOrder,
                subjectId: $order->getKey(), metadata: ['grand_total' => $order->grand_total, 'currency' => $order->currency],
                actor: $actor, subjectLabel: $order->number,
            );

            return $order->refresh();
        });
    }
}
