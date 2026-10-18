<?php

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Domain\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the read-only filters accepted by the payments list.
 *
 * `order_id` is a foreign identifier used for cross-module navigation. It only
 * narrows the tenant/store scoped query, so an order from another tenant or store
 * yields an empty page instead of leaking records.
 */
class PaymentIndexRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', Rule::enum(PaymentStatus::class)],
            'order_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
