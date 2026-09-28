<?php

namespace App\Modules\Fulfillment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelFulfillmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fulfillments.manage') ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
