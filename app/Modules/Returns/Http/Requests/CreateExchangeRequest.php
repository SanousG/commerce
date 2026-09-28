<?php

namespace App\Modules\Returns\Http\Requests;

class CreateExchangeRequest extends ManageExchangeRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.return_item_id' => ['required', 'uuid', 'distinct'],
            'items.*.replacement_product_variant_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
