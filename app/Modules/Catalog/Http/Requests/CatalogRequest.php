<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Catalog\Application\CatalogAccess;
use Illuminate\Foundation\Http\FormRequest;

abstract class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && match ($this->permission()) {
            PermissionKey::ProductsCreate => app(CatalogAccess::class)->canCreateProducts($user),
            PermissionKey::ProductsUpdate => app(CatalogAccess::class)->canUpdateProducts($user),
            PermissionKey::CategoriesCreate => app(CatalogAccess::class)->canCreateCategories($user),
            PermissionKey::CategoriesUpdate => app(CatalogAccess::class)->canUpdateCategories($user),
            default => false,
        };
    }

    abstract protected function permission(): PermissionKey;
}
