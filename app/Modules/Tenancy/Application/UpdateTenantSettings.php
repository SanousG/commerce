<?php

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class UpdateTenantSettings
{
    public function handle(Tenant $tenant, string $name): Tenant
    {
        return DB::transaction(function () use ($tenant, $name): Tenant {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
            $lockedTenant->update(['name' => trim($name)]);

            return $lockedTenant;
        });
    }
}
