<?php

namespace App\Modules\Customers\Application;

use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Customers\Domain\Models\CustomerAddress;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CreateCustomerAddress
{
    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, Customer $customer, array $attributes): CustomerAddress
    {
        return DB::transaction(function () use ($tenant, $customer, $attributes): CustomerAddress {
            $scoped = $tenant->customers()->lockForUpdate()->findOrFail($customer->getKey());
            $this->clearDefaults($scoped, $attributes);

            return $scoped->addresses()->create([
                ...$this->fields($attributes),
                'tenant_id' => $tenant->getKey(),
            ]);
        });
    }

    /** @param array<string, mixed> $attributes */
    private function clearDefaults(Customer $customer, array $attributes): void
    {
        if ($attributes['is_default_shipping'] ?? false) {
            $customer->addresses()->where('is_default_shipping', true)->update(['is_default_shipping' => false]);
        }
        if ($attributes['is_default_billing'] ?? false) {
            $customer->addresses()->where('is_default_billing', true)->update(['is_default_billing' => false]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function fields(array $attributes): array
    {
        return [
            'label' => $this->text($attributes['label'] ?? null),
            'recipient_name' => $this->text($attributes['recipient_name'] ?? null),
            'phone' => Customer::normalizePhone($attributes['phone'] ?? null),
            'country_code' => strtoupper(trim((string) $attributes['country_code'])),
            'region' => $this->text($attributes['region'] ?? null),
            'city' => trim((string) $attributes['city']),
            'district' => $this->text($attributes['district'] ?? null),
            'address_line_1' => trim((string) $attributes['address_line_1']),
            'address_line_2' => $this->text($attributes['address_line_2'] ?? null),
            'postal_code' => $this->text($attributes['postal_code'] ?? null),
            'reference' => $this->text($attributes['reference'] ?? null),
            'is_default_shipping' => (bool) ($attributes['is_default_shipping'] ?? false),
            'is_default_billing' => (bool) ($attributes['is_default_billing'] ?? false),
        ];
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
