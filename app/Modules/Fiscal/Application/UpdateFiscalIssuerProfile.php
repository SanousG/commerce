<?php

namespace App\Modules\Fiscal\Application;

use App\Modules\Fiscal\Domain\Models\FiscalIssuerProfile;
use App\Modules\Tenancy\Domain\Models\Tenant;

class UpdateFiscalIssuerProfile
{
    public function handle(Tenant $tenant, array $data): FiscalIssuerProfile
    {
        return FiscalIssuerProfile::query()->updateOrCreate(
            ['tenant_id' => $tenant->getKey()],
            $data,
        );
    }
}
