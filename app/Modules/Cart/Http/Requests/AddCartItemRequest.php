<?php

namespace App\Modules\Cart\Http\Requests;

class AddCartItemRequest extends ManageCartRequest
{
    public function rules(): array
    {
        return [
            'store_listing_id' => ['required', 'uuid'],
            'product_variant_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.min(9999, max(1, (int) config('commerce.cart_max_item_quantity', 9999)))],
        ];
    }
}
