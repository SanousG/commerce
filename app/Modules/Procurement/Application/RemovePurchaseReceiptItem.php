<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Procurement\Domain\Models\PurchaseReceiptItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemovePurchaseReceiptItem
{
    public function handle(Tenant $tenant, PurchaseReceipt $receipt, PurchaseReceiptItem $item): PurchaseReceipt
    {
        return DB::transaction(function () use ($tenant, $receipt, $item): PurchaseReceipt {
            $receipt = PurchaseReceipt::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($receipt->getKey());
            if ($receipt->status !== PurchaseReceiptStatus::Draft) {
                throw ValidationException::withMessages(['purchase_receipt' => 'Only draft receipt items can be removed.']);
            }
            PurchaseReceiptItem::query()->where('tenant_id', $tenant->getKey())->where('purchase_receipt_id', $receipt->getKey())
                ->findOrFail($item->getKey())->delete();

            return $receipt->refresh()->load('items');
        });
    }
}
