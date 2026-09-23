<?php

namespace App\Modules\Checkout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checkouts.manage') ?? false;
    }

    public function rules(): array
    {
        return ['cart_id' => ['required', 'uuid'], 'idempotency_key' => ['required', 'string', 'max:100']];
    }
}
