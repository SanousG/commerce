<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Domain\Enums\OrderFulfillmentStatus;
use App\Modules\Orders\Domain\Enums\OrderPaymentStatus;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the read-only filters accepted by the orders list.
 *
 * The store dimension is not a filter input: the list is always scoped to the
 * store resolved by StoreContext. `customer_id` and `order_id` style filters are
 * foreign identifiers coming from the browser, so they are only accepted as well
 * formed UUIDs and can never widen the tenant/store scope of the query.
 */
class OrderIndexRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['sometimes', 'nullable', Rule::enum(OrderPaymentStatus::class)],
            'fulfillment_status' => ['sometimes', 'nullable', Rule::enum(OrderFulfillmentStatus::class)],
            'customer_id' => ['sometimes', 'nullable', 'uuid'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
