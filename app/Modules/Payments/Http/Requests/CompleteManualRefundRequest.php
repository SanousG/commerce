<?php

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteManualRefundRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => trim((string) $this->input('idempotency_key')),
            'external_reference' => trim((string) $this->input('external_reference')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('refunds.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'external_reference' => ['required', 'string', 'max:255'],
        ];
    }
}
