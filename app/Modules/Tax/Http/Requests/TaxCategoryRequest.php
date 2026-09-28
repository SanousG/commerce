<?php

namespace App\Modules\Tax\Http\Requests;

use App\Modules\Tax\Domain\Enums\TaxCategoryCode;
use App\Modules\Tax\Domain\Enums\TaxCategoryStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('taxes.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => str((string) $this->input('code'))->trim()->upper()->toString(),
            'name' => trim((string) $this->input('name')),
            'status' => str((string) $this->input('status'))->trim()->upper()->toString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['prohibited'],
            'store_id' => ['prohibited'],
            'code' => [
                'required',
                Rule::enum(TaxCategoryCode::class),
                Rule::unique('tax_categories', 'code')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->ignore((string) $this->route('taxCategory')),
            ],
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::enum(TaxCategoryStatus::class)],
        ];
    }
}
