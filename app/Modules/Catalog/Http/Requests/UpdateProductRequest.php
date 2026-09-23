<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
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
        return PermissionKey::ProductsUpdate;
    }
}
