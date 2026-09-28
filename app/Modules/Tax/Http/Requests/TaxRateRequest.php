<?php

namespace App\Modules\Tax\Http\Requests;

use App\Modules\Tax\Domain\Enums\TaxCategoryStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('taxes.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tax_category_id' => trim((string) $this->input('tax_category_id')),
            'name' => trim((string) $this->input('name')),
            'valid_from' => $this->nullableTrimmed('valid_from'),
            'valid_until' => $this->nullableTrimmed('valid_until'),
            'status' => str((string) $this->input('status'))->trim()->upper()->toString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['prohibited'],
            'store_id' => ['prohibited'],
            'tax_category_id' => [
                'required',
                'uuid',
                Rule::exists('tax_categories', 'id')
                    ->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'name' => ['required', 'string', 'max:120'],
            'rate' => ['required', 'string', 'regex:/^(?:100(?:\.0{1,6})?|\d{1,2}(?:\.\d{1,6})?)$/'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => [
                'nullable',
                'date_format:Y-m-d',
                Rule::when($this->input('valid_from') !== null, 'after_or_equal:valid_from'),
            ],
            'status' => ['required', Rule::enum(TaxCategoryStatus::class)],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
