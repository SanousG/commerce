<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Domain\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the read-only filters accepted by the customers list.
 *
 * Filters only narrow the already tenant-scoped query; they never widen it, so an
 * identifier belonging to another tenant simply yields no rows.
 */
class CustomerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::enum(CustomerStatus::class)],
        ];
    }
}
