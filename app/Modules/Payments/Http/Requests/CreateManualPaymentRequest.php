<?php

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Domain\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::BankTransfer, PaymentMethod::Cash])],
            'amount' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
