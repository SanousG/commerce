<?php

namespace App\Modules\Customers\Application;

use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Customers\Domain\Models\CustomerAddress;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SetDefaultCustomerAddress
{
    public function handle(Tenant $tenant, Customer $customer, CustomerAddress $address, string $type): CustomerAddress
    {
        if (! in_array($type, ['shipping', 'billing'], true)) {
            throw new InvalidArgumentException('Unknown customer address default type.');
        }

        return DB::transaction(function () use ($tenant, $customer, $address, $type): CustomerAddress {
            $scopedCustomer = $tenant->customers()->lockForUpdate()->findOrFail($customer->getKey());
            $scopedAddress = $scopedCustomer->addresses()->findOrFail($address->getKey());
            $column = 'is_default_'.$type;
            $scopedCustomer->addresses()->where($column, true)->update([$column => false]);
            $scopedAddress->update([$column => true]);

            return $scopedAddress;
        });
    }
}
