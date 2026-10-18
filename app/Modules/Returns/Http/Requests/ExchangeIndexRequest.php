<?php

namespace App\Modules\Returns\Http\Requests;

use App\Modules\Returns\Domain\Enums\ExchangeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the read-only filters accepted by the exchanges list.
 *
 * `order_id` supports cross-module navigation from an order. It never widens the
 * tenant/store scope of the query.
 */
class ExchangeIndexRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', Rule::enum(ExchangeStatus::class)],
            'order_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
