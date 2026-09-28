<?php

namespace App\Modules\Fulfillment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fulfillments.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'inventory_location_id' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_reservation_id' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
