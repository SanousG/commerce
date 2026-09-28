<?php

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Domain\Enums\CodDepositType;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'bank_transfer_enabled' => ['required', 'boolean'],
            'cash_enabled' => ['required', 'boolean'],
            'online_enabled' => ['required', 'boolean'],
            'cod_enabled' => ['required', 'boolean'],
            'cod_deposit_type' => ['required', Rule::enum(CodDepositType::class)],
            'cod_deposit_value' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = CodDepositType::tryFrom((string) $this->input('cod_deposit_type'));
            if ($type === null || ! is_string($this->input('cod_deposit_value'))
                || $validator->errors()->has('cod_deposit_value')) {
                return;
            }
            $value = BigDecimal::of($this->input('cod_deposit_value'));
            $invalid = match ($type) {
                CodDepositType::None => ! $value->isZero(),
                CodDepositType::Percentage => $value->isLessThanOrEqualTo(0) || $value->isGreaterThan(100),
                CodDepositType::FixedAmount => $value->isLessThanOrEqualTo(0),
            };
            if ($invalid) {
                $validator->errors()->add('cod_deposit_value', 'The COD deposit value is invalid for its type.');
            }
        }];
    }
}
