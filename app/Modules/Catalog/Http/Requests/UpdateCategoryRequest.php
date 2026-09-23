<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Catalog\Domain\Enums\CategoryStatus;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', Rule::enum(CategoryStatus::class)],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::notIn([(string) $this->route('category')]),
                Rule::exists('categories', 'id')->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::CategoriesUpdate;
    }
}
