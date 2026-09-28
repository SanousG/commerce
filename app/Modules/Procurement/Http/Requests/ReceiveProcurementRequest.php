<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveProcurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.receive') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
