<?php

namespace App\Modules\Procurement\Application;

use App\Models\User;
use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Inventory\Domain\Models\InventoryLocation;
use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePurchaseReceipt
{
    public function handle(Tenant $tenant, PurchaseReceipt $receipt, InventoryLocation $location, string $receivedAt, User $receivedBy, ?string $reference = null, ?string $notes = null): PurchaseReceipt
    {
        return DB::transaction(function () use ($tenant, $receipt, $location, $receivedAt, $receivedBy, $reference, $notes): PurchaseReceipt {
            $receipt = PurchaseReceipt::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($receipt->getKey());
            if ($receipt->status !== PurchaseReceiptStatus::Draft) {
                throw ValidationException::withMessages(['purchase_receipt' => 'Only a draft purchase receipt can be edited.']);
            }
            $location = InventoryLocation::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($location->getKey());
            if ($location->status !== InventoryLocationStatus::Active) {
                throw ValidationException::withMessages(['inventory_location_id' => 'Receipts require an active tenant inventory location.']);
            }
            $receipt->update([
                'inventory_location_id' => $location->getKey(), 'received_at' => $receivedAt,
                'reference' => $reference, 'notes' => $notes, 'received_by_user_id' => $receivedBy->getKey(),
            ]);

            return $receipt->refresh();
        });
    }
}
