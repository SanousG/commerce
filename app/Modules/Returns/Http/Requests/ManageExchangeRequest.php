<?php

namespace App\Modules\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exchanges.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
