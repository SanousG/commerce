<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSupplier
{
    public function handle(Tenant $tenant, Supplier $supplier, string $code, string $name, SupplierStatus $status, ?string $contactName = null, ?string $email = null, ?string $phone = null, ?string $website = null, ?string $notes = null): Supplier
    {
        if ($status === SupplierStatus::Archived) {
            throw ValidationException::withMessages(['status' => 'Use the supplier archive operation so open-order checks are enforced.']);
        }

        return DB::transaction(function () use ($tenant, $supplier, $code, $name, $status, $contactName, $email, $phone, $website, $notes): Supplier {
            $supplier = Supplier::query()->where('tenant_id', $tenant->getKey())->lockForUpdate()->findOrFail($supplier->getKey());
            $supplier->update([
                'code' => strtoupper(trim($code)), 'name' => trim($name), 'status' => $status,
                'contact_name' => $contactName, 'email' => $email, 'phone' => $phone,
                'website' => $website, 'notes' => $notes,
            ]);

            return $supplier->refresh();
        });
    }
}
