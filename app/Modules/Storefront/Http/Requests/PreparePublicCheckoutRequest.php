<?php

namespace App\Modules\Storefront\Http\Requests;

use App\Modules\Storefront\Application\PublicCartContext;
use Illuminate\Foundation\Http\FormRequest;

final class PreparePublicCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PublicCartContext::class)->cart() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'customer_name' => $this->text('customer_name'),
            'email' => filled($this->input('email')) ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $this->phone('phone'),
            'billing_country_code' => filled($this->input('billing_country_code'))
                ? strtoupper(trim((string) $this->input('billing_country_code'))) : null,
        ];

        foreach (['billing'] as $prefix) {
            foreach (['region', 'city', 'district', 'address_line_1', 'address_line_2', 'postal_code', 'reference'] as $field) {
                $normalized["{$prefix}_{$field}"] = $this->text("{$prefix}_{$field}");
            }
        }

        $normalized['billing_recipient_name'] = $this->text('billing_recipient_name');
        $normalized['billing_phone'] = $this->phone('billing_phone');
        $this->merge($normalized);
    }

    public function rules(): array
    {
        $nullableText = ['nullable', 'string', 'max:255'];

        return [
            'mutation_id' => ['required', 'uuid'],
            'customer_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'required_without:email', 'regex:/^\+?[0-9]{6,31}$/'],
            'shipping_recipient_name' => ['prohibited'],
            'shipping_phone' => ['prohibited'],
            'shipping_country_code' => ['prohibited'],
            'shipping_region' => ['prohibited'],
            'shipping_city' => ['prohibited'],
            'shipping_district' => ['prohibited'],
            'shipping_address_line_1' => ['prohibited'],
            'shipping_address_line_2' => ['prohibited'],
            'shipping_postal_code' => ['prohibited'],
            'shipping_reference' => ['prohibited'],
            'billing_same_as_shipping' => ['prohibited'],
            'billing_recipient_name' => ['nullable', 'string', 'max:160'],
            'billing_phone' => ['nullable', 'regex:/^\+?[0-9]{6,31}$/'],
            'billing_country_code' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'billing_region' => $nullableText,
            'billing_city' => $nullableText,
            'billing_district' => $nullableText,
            'billing_address_line_1' => $nullableText,
            'billing_address_line_2' => $nullableText,
            'billing_postal_code' => ['nullable', 'string', 'max:32'],
            'billing_reference' => $nullableText,
        ];
    }

    private function text(string $key): ?string
    {
        return filled($this->input($key)) ? trim((string) $this->input($key)) : null;
    }

    private function phone(string $key): ?string
    {
        return filled($this->input($key))
            ? preg_replace('/[\s().-]+/', '', (string) $this->input($key))
            : null;
    }
}
