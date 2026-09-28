<?php

namespace App\Modules\Returns\Http\Requests;

class CancelReturnRequest extends ManageReturnRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
