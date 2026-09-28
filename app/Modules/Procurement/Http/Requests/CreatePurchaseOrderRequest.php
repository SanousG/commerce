<?php

namespace App\Modules\Procurement\Http\Requests;

use App\Modules\Procurement\Domain\Enums\SupplierProductStatus;
use App\Modules\Procurement\Domain\Enums\SupplierStatus;
use App\Modules\Procurement\Domain\Models\SupplierProduct;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $items = collect((array) $this->input('items', []))->map(function ($item): array {
            $item = (array) $item;
            if (array_key_exists('unit_cost', $item)) {
                $item['unit_cost'] = trim((string) $item['unit_cost']);
            }
            if (array_key_exists('description', $item)) {
                $description = trim((string) $item['description']);
                $item['description'] = $description === '' ? null : $description;
            }

            return $item;
        })->values()->all();

        $currency = $this->filled('currency') ? (string) $this->input('currency') : null;
        if ($currency === null && isset($items[0]['supplier_product_id'])) {
            $currency = SupplierProduct::query()->where('tenant_id', app(TenantContext::class)->id())
                ->whereKey($items[0]['supplier_product_id'])->value('currency');
        }

        $this->merge([
            'currency' => $currency === null ? null : str($currency)->trim()->upper()->toString(),
            'expected_at' => $this->filled('expected_at') ? $this->input('expected_at') : null,
            'notes' => $this->nullableTrimmed('notes'),
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'supplier_id' => ['required', 'uuid', Rule::exists('suppliers', 'id')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', SupplierStatus::Active->value))],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:250'],
            'items.*.supplier_product_id' => ['required', 'uuid', 'distinct', Rule::exists('supplier_products', 'id')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', SupplierProductStatus::Active->value))],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'string', 'regex:/^\d{1,15}(?:\.\d{1,4})?$/'],
            'items.*.description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
