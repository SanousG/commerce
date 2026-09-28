<?php

namespace App\Modules\Fiscal\Http\Requests;

use App\Modules\Fiscal\Domain\Enums\FiscalConfigurationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiscalIssuerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fiscal_settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'country_code' => str((string) $this->input('country_code'))->trim()->upper()->toString(),
            'tax_id' => trim((string) $this->input('tax_id')),
            'legal_name' => trim((string) $this->input('legal_name')),
            'fiscal_address_line_1' => trim((string) $this->input('fiscal_address_line_1')),
            'status' => str((string) $this->input('status'))->trim()->upper()->toString(),
        ];
        foreach (['trade_name', 'fiscal_address_line_2', 'district', 'province', 'region', 'postal_code', 'ubigeo'] as $field) {
            $data[$field] = $this->nullableTrimmed($field);
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['prohibited'],
            'store_id' => ['prohibited'],
            'country_code' => ['required', 'string', 'regex:/^[A-Z]{2}$/'],
            'tax_id' => ['required', 'string', 'max:80'],
            'legal_name' => ['required', 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'fiscal_address_line_1' => ['required', 'string', 'max:255'],
            'fiscal_address_line_2' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'ubigeo' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::enum(FiscalConfigurationStatus::class)],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
