<?php

namespace App\Modules\Inventory\Http\Requests;

class AdjustInventoryRequest extends InventoryRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }

    public function rules(): array
    {
        return [
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
