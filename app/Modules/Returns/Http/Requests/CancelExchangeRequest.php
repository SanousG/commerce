<?php

namespace App\Modules\Returns\Http\Requests;

class CancelExchangeRequest extends ManageExchangeRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
