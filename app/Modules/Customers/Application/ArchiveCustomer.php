<?php

namespace App\Modules\Customers\Application;

use App\Modules\Customers\Domain\Enums\CustomerStatus;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Tenancy\Domain\Models\Tenant;

class ArchiveCustomer
{
    public function handle(Tenant $tenant, Customer $customer): Customer
    {
        $scoped = $tenant->customers()->findOrFail($customer->getKey());
        $scoped->update(['status' => CustomerStatus::Archived]);

        return $scoped;
    }
}
