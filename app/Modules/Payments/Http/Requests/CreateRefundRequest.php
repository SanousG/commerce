<?php

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRefundRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
            'idempotency_key' => trim((string) $this->input('idempotency_key')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('refunds.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'return_id' => ['nullable', 'uuid'],
        ];
    }
}
