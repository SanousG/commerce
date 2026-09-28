<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcurementActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
