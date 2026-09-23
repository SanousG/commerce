<?php

namespace App\Modules\Checkout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checkouts.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
