<?php

namespace App\Modules\Orders\Application;

use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class GenerateOrderNumber
{
    /** Must run inside the transaction that creates the Order. */
    public function handle(Tenant $tenant): string
    {
        $now = now();
        DB::table('order_number_sequences')->insertOrIgnore([
            'tenant_id' => $tenant->getKey(), 'next_number' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sequence = DB::table('order_number_sequences')->where('tenant_id', $tenant->getKey())
            ->lockForUpdate()->firstOrFail();
        DB::table('order_number_sequences')->where('tenant_id', $tenant->getKey())->update([
            'next_number' => $sequence->next_number + 1, 'updated_at' => $now,
        ]);

        return 'ORD-'.str_pad((string) $sequence->next_number, 6, '0', STR_PAD_LEFT);
    }
}
