<?php

namespace App\Modules\Returns\Http\Requests;

class ReceiveReturnRequest extends ManageReturnRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.return_item_id' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
