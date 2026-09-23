<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Validation\Rule;

class CreateProductRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'sku' => str((string) $this->input('sku'))->trim()->upper()->toString(),
            'barcode' => $this->filled('barcode') ? trim((string) $this->input('barcode')) : null,
        ]);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'sku' => ['required', 'string', 'max:120', Rule::unique('product_variants')->where('tenant_id', $tenantId)],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('product_variants')->where('tenant_id', $tenantId)],
            'categories' => ['sometimes', 'array', 'max:50'],
            'categories.*' => [
                'uuid',
                'distinct',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::ProductsCreate;
    }
}
