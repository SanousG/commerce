<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class GeneratePurchaseOrderNumber
{
    /** Must run inside the transaction that creates the purchase order. */
    public function handle(Tenant $tenant): string
    {
        $now = now();
        DB::table('purchase_order_number_sequences')->insertOrIgnore([
            'tenant_id' => $tenant->getKey(), 'next_number' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sequence = DB::table('purchase_order_number_sequences')->where('tenant_id', $tenant->getKey())->lockForUpdate()->firstOrFail();
        DB::table('purchase_order_number_sequences')->where('tenant_id', $tenant->getKey())->update([
            'next_number' => $sequence->next_number + 1, 'updated_at' => $now,
        ]);

        return 'PO-'.str_pad((string) $sequence->next_number, 6, '0', STR_PAD_LEFT);
    }
}
