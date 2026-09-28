<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'expected_at' => $this->filled('expected_at') ? $this->input('expected_at') : null,
            'notes' => $this->nullableTrimmed('notes'),
        ];
        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'discount_total' => ['prohibited'],
            'shipping_total' => ['prohibited'],
            'tax_total' => ['prohibited'],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
