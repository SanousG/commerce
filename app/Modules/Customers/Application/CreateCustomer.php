<?php

namespace App\Modules\Customers\Application;

use App\Modules\Customers\Domain\Enums\CustomerStatus;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Tenancy\Domain\Models\Tenant;

class CreateCustomer
{
    /** @param array<string, mixed> $attributes */
    public function handle(Tenant $tenant, array $attributes): Customer
    {
        return $tenant->customers()->create([
            ...$this->fields($attributes),
            'status' => CustomerStatus::Active,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function fields(array $attributes): array
    {
        return [
            'first_name' => $this->text($attributes['first_name'] ?? null),
            'last_name' => $this->text($attributes['last_name'] ?? null),
            'email' => Customer::normalizeEmail($attributes['email'] ?? null),
            'phone' => Customer::normalizePhone($attributes['phone'] ?? null),
            'notes' => $this->text($attributes['notes'] ?? null),
        ];
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
