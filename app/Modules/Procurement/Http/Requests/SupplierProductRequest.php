<?php

namespace App\Modules\Procurement\Http\Requests;

use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('suppliers.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $data = ['currency' => str((string) $this->input('currency'))->trim()->upper()->toString()];
        foreach (['supplier_sku', 'notes'] as $field) {
            if ($this->has($field) || $this->route('supplier') !== null) {
                $data[$field] = $this->nullableTrimmed($field);
            }
        }
        if ($this->has('is_preferred')) {
            $data['is_preferred'] = filter_var($this->input('is_preferred'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $creating = $this->route('supplier') !== null;
        $tenantId = app(TenantContext::class)->id();

        return [
            'product_variant_id' => [$creating ? 'required' : 'sometimes', 'uuid', Rule::exists('product_variants', 'id')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', ProductVariantStatus::Active->value))],
            'supplier_sku' => ['nullable', 'string', 'max:100'],
            'unit_cost' => ['required', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'minimum_order_quantity' => ['required', 'integer', 'min:1'],
            'order_multiple' => ['nullable', 'integer', 'min:1'],
            'lead_time_days' => ['nullable', 'integer', 'between:0,3650'],
            'status' => ['sometimes', Rule::in([
                SupplierProductStatus::Active->value,
                SupplierProductStatus::Inactive->value,
            ])],
            'is_preferred' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
