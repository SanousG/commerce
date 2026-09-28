<?php

namespace App\Modules\Procurement\Application;

use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\Supplier;
use App\Modules\Tenancy\Domain\Models\Tenant;

class CreateSupplier
{
    public function handle(Tenant $tenant, string $code, string $name, ?string $contactName = null, ?string $email = null, ?string $phone = null, ?string $website = null, ?string $notes = null): Supplier
    {
        return Supplier::query()->create([
            'tenant_id' => $tenant->getKey(), 'code' => strtoupper(trim($code)), 'name' => trim($name),
            'status' => SupplierStatus::Active, 'contact_name' => $contactName, 'email' => $email,
            'phone' => $phone, 'website' => $website, 'notes' => $notes,
        ]);
    }
}
