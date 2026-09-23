<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Validation\Rule;

class CreateCategoryRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('categories', 'id')->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::CategoriesCreate;
    }
}
