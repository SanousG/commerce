<?php

namespace App\Modules\Customers\Application;

use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Tenancy\Domain\Models\Tenant;

class UpdateCustomer
{
    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, Customer $customer, array $attributes): Customer
    {
        $scoped = $tenant->customers()->findOrFail($customer->getKey());
        $scoped->update([
            'first_name' => $this->text($attributes['first_name'] ?? null),
            'last_name' => $this->text($attributes['last_name'] ?? null),
            'email' => Customer::normalizeEmail($attributes['email'] ?? null),
            'phone' => Customer::normalizePhone($attributes['phone'] ?? null),
            'status' => $attributes['status'],
            'notes' => $this->text($attributes['notes'] ?? null),
        ]);

        return $scoped;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
