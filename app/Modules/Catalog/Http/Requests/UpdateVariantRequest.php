<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Catalog\Domain\Enums\ProductVariantStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Validation\Rule;

class UpdateVariantRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => str((string) $this->input('sku'))->trim()->upper()->toString(),
            'barcode' => $this->filled('barcode') ? trim((string) $this->input('barcode')) : null,
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
        ]);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $variantId = (string) $this->route('variant');

        return [
            'sku' => ['required', 'string', 'max:120', Rule::unique('product_variants')->where('tenant_id', $tenantId)->ignore($variantId)],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('product_variants')->where('tenant_id', $tenantId)->ignore($variantId)],
            'name' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::enum(ProductVariantStatus::class)],
            'is_default' => ['required', 'boolean'],
            'option_value_ids' => ['sometimes', 'array', 'max:20'],
            'option_value_ids.*' => ['uuid', 'distinct'],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::ProductsUpdate;
    }
}
