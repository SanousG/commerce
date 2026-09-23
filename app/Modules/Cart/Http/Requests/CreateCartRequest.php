<?php

namespace App\Modules\Cart\Http\Requests;

class CreateCartRequest extends ManageCartRequest
{
    public function rules(): array
    {
        return ['customer_id' => ['nullable', 'uuid']];
    }
}
