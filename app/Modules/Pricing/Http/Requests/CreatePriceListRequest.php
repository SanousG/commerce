<?php

namespace App\Modules\Pricing\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Validation\Rule;

class CreatePriceListRequest extends PricingRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => str((string) $this->input('code'))->trim()->upper()->toString(),
            'currency' => str((string) $this->input('currency'))->trim()->upper()->toString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('price_lists')->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'currency' => ['required', 'string', Rule::in(config('commerce.currencies', []))],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::PricingCreate;
    }
}
