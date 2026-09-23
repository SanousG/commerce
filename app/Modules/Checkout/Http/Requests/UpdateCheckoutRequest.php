<?php

namespace App\Modules\Checkout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checkouts.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->email) ? strtolower(trim((string) $this->email)) : null,
            'phone' => filled($this->phone) ? preg_replace('/[\s().-]+/', '', (string) $this->phone) : null,
            'shipping_country_code' => filled($this->shipping_country_code) ? strtoupper((string) $this->shipping_country_code) : null,
            'billing_country_code' => filled($this->billing_country_code) ? strtoupper((string) $this->billing_country_code) : null,
        ]);
    }

    public function rules(): array
    {
        $nullableText = ['nullable', 'string', 'max:255'];

        return [
            'customer_name' => ['nullable', 'string', 'max:160'], 'email' => ['nullable', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'regex:/^\+?[0-9]{6,31}$/'],
            'delivery_type' => ['required', Rule::in(['SHIPPING', 'PICKUP'])],
            'shipping_address_id' => ['nullable', 'uuid'], 'billing_address_id' => ['nullable', 'uuid'],
            'billing_same_as_shipping' => ['sometimes', 'boolean'],
            'shipping_recipient_name' => ['nullable', 'string', 'max:160'], 'shipping_phone' => ['nullable', 'string', 'max:32'],
            'shipping_country_code' => ['nullable', 'regex:/^[A-Z]{2}$/'], 'shipping_region' => $nullableText,
            'shipping_city' => $nullableText, 'shipping_district' => $nullableText, 'shipping_address_line_1' => $nullableText,
            'shipping_address_line_2' => $nullableText, 'shipping_postal_code' => ['nullable', 'string', 'max:32'],
            'shipping_reference' => $nullableText,
            'billing_recipient_name' => ['nullable', 'string', 'max:160'], 'billing_phone' => ['nullable', 'string', 'max:32'],
            'billing_country_code' => ['nullable', 'regex:/^[A-Z]{2}$/'], 'billing_region' => $nullableText,
            'billing_city' => $nullableText, 'billing_district' => $nullableText, 'billing_address_line_1' => $nullableText,
            'billing_address_line_2' => $nullableText, 'billing_postal_code' => ['nullable', 'string', 'max:32'],
            'billing_reference' => $nullableText,
        ];
    }
}
