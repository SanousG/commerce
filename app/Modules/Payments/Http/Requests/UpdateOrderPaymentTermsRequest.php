<?php

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Domain\Enums\PaymentTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderPaymentTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.manage') ?? false;
    }

    public function rules(): array
    {
        return ['terms' => ['required', Rule::enum(PaymentTerms::class)]];
    }
}
