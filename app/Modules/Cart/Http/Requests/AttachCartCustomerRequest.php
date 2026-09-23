<?php

namespace App\Modules\Cart\Http\Requests;

class AttachCartCustomerRequest extends ManageCartRequest
{
    public function rules(): array
    {
        return ['customer_id' => ['nullable', 'uuid']];
    }
}
