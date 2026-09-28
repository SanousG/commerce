<?php

namespace App\Modules\Procurement\Http\Requests;

use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends CreateSupplierRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('suppliers.update') ?? false;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', Rule::in([SupplierStatus::Active->value, SupplierStatus::Inactive->value])],
        ];
    }
}
