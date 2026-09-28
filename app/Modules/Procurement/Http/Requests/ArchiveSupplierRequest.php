<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('suppliers.archive') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
