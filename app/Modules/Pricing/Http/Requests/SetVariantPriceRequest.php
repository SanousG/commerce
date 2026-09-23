<?php

namespace App\Modules\Pricing\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;

class SetVariantPriceRequest extends PricingRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => trim((string) $this->input('amount'))]);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::PricingUpdate;
    }
}
