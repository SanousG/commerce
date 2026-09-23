<?php

namespace App\Modules\Catalog\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;

class CatalogAccess
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function canViewProducts(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::ProductsView);
    }

    public function canCreateProducts(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::ProductsCreate);
    }

    public function canUpdateProducts(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::ProductsUpdate);
    }

    public function canArchiveProducts(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::ProductsArchive);
    }

    public function canViewCategories(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CategoriesView);
    }

    public function canCreateCategories(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CategoriesCreate);
    }

    public function canUpdateCategories(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CategoriesUpdate);
    }

    public function canArchiveCategories(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CategoriesArchive);
    }
}
