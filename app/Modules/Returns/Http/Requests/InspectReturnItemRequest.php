<?php

namespace App\Modules\Returns\Http\Requests;

use App\Modules\Returns\Domain\Enums\ReturnItemCondition;
use App\Modules\Returns\Domain\Enums\ReturnItemDisposition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InspectReturnItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('returns.inspect') ?? false;
    }

    public function rules(): array
    {
        return [
            'condition' => ['required', Rule::enum(ReturnItemCondition::class)],
            'disposition' => ['required', Rule::enum(ReturnItemDisposition::class)],
            'accepted_quantity' => ['required', 'integer', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
