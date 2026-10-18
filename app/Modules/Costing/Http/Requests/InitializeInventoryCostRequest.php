<?php

namespace App\Modules\Costing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitializeInventoryCostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('inventory.cost.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'unit_cost' => ['required', 'decimal:0,6', 'gte:0'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
        ];
    }
}
