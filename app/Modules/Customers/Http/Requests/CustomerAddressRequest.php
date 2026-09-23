<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Application\CustomerAccess;
use App\Modules\Customers\Domain\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CustomerAccess::class)->canUpdate($user);
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['label', 'recipient_name', 'region', 'district', 'address_line_2', 'postal_code', 'reference'];
        $values = [];
        foreach ($nullable as $key) {
            $value = trim((string) $this->input($key));
            $values[$key] = $value === '' ? null : $value;
        }
        $values['phone'] = Customer::normalizePhone($this->input('phone'));
        $values['country_code'] = strtoupper(trim((string) $this->input('country_code')));
        $values['city'] = trim((string) $this->input('city'));
        $values['address_line_1'] = trim((string) $this->input('address_line_1'));
        $values['is_default_shipping'] = $this->boolean('is_default_shipping');
        $values['is_default_billing'] = $this->boolean('is_default_billing');
        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:80'],
            'recipient_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{6,31}$/'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'region' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'address_line_1' => ['required', 'string', 'max:240'],
            'address_line_2' => ['nullable', 'string', 'max:240'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:500'],
            'is_default_shipping' => ['required', 'boolean'],
            'is_default_billing' => ['required', 'boolean'],
        ];
    }
}
