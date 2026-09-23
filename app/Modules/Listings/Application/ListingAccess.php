<?php

namespace App\Modules\Listings\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;

class ListingAccess
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function canView(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::ListingsView);
    }

    public function canCreate(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::ListingsCreate);
    }

    public function canUpdate(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::ListingsUpdate);
    }

    public function canPublish(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::ListingsPublish);
    }

    public function canArchive(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::ListingsArchive);
    }
}
