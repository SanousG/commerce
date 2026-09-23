<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Domain\Enums\CustomerStatus;
use App\Modules\Customers\Domain\Models\Customer;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class SaveCustomerRequest extends CustomerRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->nullableTrimmed('first_name'),
            'last_name' => $this->nullableTrimmed('last_name'),
            'email' => Customer::normalizeEmail($this->input('email')),
            'phone' => Customer::normalizePhone($this->input('phone')),
            'notes' => $this->nullableTrimmed('notes'),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{6,31}$/'],
            'status' => [$this->isMethod('post') ? 'sometimes' : 'required', Rule::enum(CustomerStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('first_name') === null && $this->input('last_name') === null
                && $this->input('email') === null && $this->input('phone') === null) {
                $validator->errors()->add('first_name', 'Provide a name, email, or phone.');
            }

            $tenant = app(TenantContext::class)->get();
            $customerId = $this->route('customer');
            foreach (['email', 'phone'] as $field) {
                $value = $this->input($field);
                if ($value === null || $validator->errors()->has($field)) {
                    continue;
                }
                $query = $tenant->customers()->where($field, $value);
                if (is_string($customerId)) {
                    $query->whereKeyNot($customerId);
                }
                if ($query->exists()) {
                    $validator->errors()->add($field, "The {$field} has already been taken.");
                }
            }
        });
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
