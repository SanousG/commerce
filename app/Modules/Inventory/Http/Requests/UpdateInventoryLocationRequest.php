<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Validation\Rule;

class UpdateInventoryLocationRequest extends InventoryRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => str((string) $this->input('code'))->trim()->upper()->toString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('inventory_locations')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->ignore((string) $this->route('location')),
            ],
            'status' => ['required', Rule::enum(InventoryLocationStatus::class)],
        ];
    }
}
