<?php

namespace App\Modules\Procurement\Http\Requests;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReceiptItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.receive') ?? false;
    }

    public function rules(): array
    {
        $creating = $this->route('receipt') !== null;

        return [
            'purchase_order_item_id' => [$creating ? 'required' : 'sometimes', 'uuid', Rule::exists('purchase_order_items', 'id')
                ->where('tenant_id', app(TenantContext::class)->id())],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
