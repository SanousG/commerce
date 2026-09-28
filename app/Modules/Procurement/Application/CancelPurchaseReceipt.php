<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\PurchaseReceiptStatus;
use App\Modules\Procurement\Domain\Models\PurchaseReceipt;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelPurchaseReceipt
{
    public function handle(Tenant $tenant, PurchaseReceipt $receipt): PurchaseReceipt
    {
        return DB::transaction(function () use ($tenant, $receipt): PurchaseReceipt {
            $receipt = PurchaseReceipt::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($receipt->getKey());
            if ($receipt->status !== PurchaseReceiptStatus::Draft) {
                throw ValidationException::withMessages(['purchase_receipt' => 'Only a draft receipt can be cancelled.']);
            }
            $receipt->update(['status' => PurchaseReceiptStatus::Cancelled]);

            return $receipt->refresh();
        });
    }
}
