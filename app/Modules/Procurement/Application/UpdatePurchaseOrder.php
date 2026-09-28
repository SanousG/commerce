<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePurchaseOrder
{
    public function __construct(private readonly PurchaseOrderCalculator $calculator) {}

    public function handle(Tenant $tenant, PurchaseOrder $order, ?string $expectedAt = null, ?string $notes = null): PurchaseOrder
    {
        return DB::transaction(function () use ($tenant, $order, $expectedAt, $notes): PurchaseOrder {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            if ($order->status !== PurchaseOrderStatus::Draft) {
                throw ValidationException::withMessages(['purchase_order' => 'Only a draft purchase order can be edited.']);
            }
            $order->update(['expected_at' => $expectedAt, 'notes' => $notes]);

            return $this->calculator->handle($order, '0', '0', '0');
        });
    }
}
