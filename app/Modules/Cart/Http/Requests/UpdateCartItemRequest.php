<?php

namespace App\Modules\Cart\Http\Requests;

class UpdateCartItemRequest extends ManageCartRequest
{
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.min(9999, max(1, (int) config('commerce.cart_max_item_quantity', 9999)))],
        ];
    }
}
