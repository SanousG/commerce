<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class ArchiveSupplier
{
    public function handle(Tenant $tenant, Supplier $supplier): Supplier
    {
        return DB::transaction(function () use ($tenant, $supplier): Supplier {
            $supplier = Supplier::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($supplier->getKey());
            $supplier->update(['status' => SupplierStatus::Archived]);

            return $supplier->refresh();
        });
    }
}
