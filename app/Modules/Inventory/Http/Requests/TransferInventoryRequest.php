<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Validation\Rule;

class TransferInventoryRequest extends InventoryRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) {
            $this->merge(['notes' => trim((string) $this->input('notes')) ?: null]);
        }
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $locationExists = fn () => Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId);

        return [
            'source_location_id' => ['required', 'uuid', $locationExists()],
            'destination_location_id' => [
                'required', 'uuid', 'different:source_location_id', $locationExists(),
            ],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_variant_id' => [
                'required', 'uuid', 'distinct:strict',
                Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId),
            ],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
