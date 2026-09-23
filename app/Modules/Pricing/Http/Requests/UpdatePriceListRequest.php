<?php

namespace App\Modules\Pricing\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends PricingRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'currency' => str((string) $this->input('currency'))->trim()->upper()->toString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', Rule::in(config('commerce.currencies', []))],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::PricingUpdate;
    }
}
