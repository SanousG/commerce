<?php

namespace App\Modules\Procurement\Http\Requests;

use App\Modules\Inventory\Domain\Enums\InventoryLocationStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.receive') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'received_at' => $this->filled('received_at') ? $this->input('received_at') : now()->toISOString(),
            'reference' => $this->nullableTrimmed('reference'),
            'notes' => $this->nullableTrimmed('notes'),
        ];
        if ($this->route('purchaseOrder') !== null) {
            $data['items'] = collect((array) $this->input('items', []))->map(fn ($item): array => (array) $item)->values()->all();
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $creating = $this->route('purchaseOrder') !== null;
        $tenantId = app(TenantContext::class)->id();

        return [
            'inventory_location_id' => ['required', 'uuid', Rule::exists('inventory_locations', 'id')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', InventoryLocationStatus::Active->value))],
            'received_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'items' => [$creating ? 'sometimes' : 'prohibited', 'array', 'max:250'],
            'items.*.purchase_order_item_id' => ['required', 'uuid', 'distinct', Rule::exists('purchase_order_items', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
