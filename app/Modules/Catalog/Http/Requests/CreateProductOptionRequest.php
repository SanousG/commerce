<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;

class CreateProductOptionRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'values' => collect($this->input('values', []))
                ->map(fn ($value) => trim((string) $value))
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'values' => ['required', 'array', 'min:1', 'max:50'],
            'values.*' => ['required', 'string', 'max:100', 'distinct'],
        ];
    }

    protected function permission(): PermissionKey
    {
        return PermissionKey::ProductsUpdate;
    }
}
