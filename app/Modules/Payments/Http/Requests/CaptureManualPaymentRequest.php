<?php

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaptureManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.capture') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
