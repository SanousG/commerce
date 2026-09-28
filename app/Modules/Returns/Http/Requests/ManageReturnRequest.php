<?php

namespace App\Modules\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('returns.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
