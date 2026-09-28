<?php

namespace App\Modules\Procurement\Application;

use App\Models\User;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Procurement\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseOrder;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePurchaseReceipt
{
    public function handle(Tenant $tenant, PurchaseOrder $order, InventoryLocation $location, string $receivedAt, User $receivedBy, ?string $reference = null, ?string $notes = null): PurchaseReceipt
    {
        return DB::transaction(function () use ($tenant, $order, $location, $receivedAt, $receivedBy, $reference, $notes): PurchaseReceipt {
            $order = PurchaseOrder::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($order->getKey());
            $location = InventoryLocation::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($location->getKey());
            if (! in_array($order->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true)) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Receipts require an ordered purchase order with an open balance.']);
            }
            if ($location->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['inventory_location_id' => 'Receipts require an active tenant inventory location.']);
            }

            return PurchaseReceipt::query()->create([
                'tenant_id' => $tenant->getKey(), 'purchase_order_id' => $order->getKey(),
                'supplier_id' => $order->supplier_id,
                'inventory_location_id' => $location->getKey(), 'status' => PurchaseReceiptStatus::Draft,
                'received_at' => $receivedAt, 'reference' => $reference, 'notes' => $notes,
                'received_by_user_id' => $receivedBy->getKey(),
            ]);
        });
    }
}
