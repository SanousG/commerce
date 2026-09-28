<?php

namespace App\Modules\Procurement\Http\Requests;

use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $data = ['unit_cost' => $this->filled('unit_cost') ? trim((string) $this->input('unit_cost')) : null];
        if ($this->has('description') || $this->route('purchaseOrder') !== null) {
            $data['description'] = $this->nullableTrimmed('description');
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $creating = $this->route('purchaseOrder') !== null;
        $tenantId = app(TenantContext::class)->id();

        return [
            'supplier_product_id' => [$creating ? 'required' : 'sometimes', 'uuid', Rule::exists('supplier_products', 'id')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', SupplierProductStatus::Active->value))],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['nullable', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
