<?php

namespace App\Modules\Customers\Application;

use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Customers\Domain\Models\CustomerAddress;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class UpdateCustomerAddress
{
    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, Customer $customer, CustomerAddress $address, array $attributes): CustomerAddress
    {
        return DB::transaction(function () use ($tenant, $customer, $address, $attributes): CustomerAddress {
            $scopedCustomer = $tenant->customers()->lockForUpdate()->findOrFail($customer->getKey());
            $scopedAddress = $scopedCustomer->addresses()->findOrFail($address->getKey());
            if ($attributes['is_default_shipping'] ?? false) {
                $scopedCustomer->addresses()->whereKeyNot($scopedAddress->getKey())
                    ->where('is_default_shipping', true)->update(['is_default_shipping' => false]);
            }
            if ($attributes['is_default_billing'] ?? false) {
                $scopedCustomer->addresses()->whereKeyNot($scopedAddress->getKey())
                    ->where('is_default_billing', true)->update(['is_default_billing' => false]);
            }
            $scopedAddress->update($this->fields($attributes));

            return $scopedAddress;
        });
    }

    /** @param array<string, mixed> $attributes */
    private function fields(array $attributes): array
    {
        $fields = [];
        foreach (['label', 'recipient_name', 'region', 'district', 'address_line_2', 'postal_code', 'reference'] as $key) {
            $value = trim((string) ($attributes[$key] ?? ''));
            $fields[$key] = $value === '' ? null : $value;
        }

        return [
            ...$fields,
            'phone' => Customer::normalizePhone($attributes['phone'] ?? null),
            'country_code' => strtoupper(trim((string) $attributes['country_code'])),
            'city' => trim((string) $attributes['city']),
            'address_line_1' => trim((string) $attributes['address_line_1']),
            'is_default_shipping' => (bool) ($attributes['is_default_shipping'] ?? false),
            'is_default_billing' => (bool) ($attributes['is_default_billing'] ?? false),
        ];
    }
}
