<?php

namespace App\Modules\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestockReturnItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('returns.restock') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_location_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
