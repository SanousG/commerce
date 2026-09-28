<?php

namespace App\Modules\Returns\Http\Requests;

class CreateReturnRequest extends ManageReturnRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
