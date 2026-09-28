<?php

namespace App\Modules\Procurement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.approve') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
