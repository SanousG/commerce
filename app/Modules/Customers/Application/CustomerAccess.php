<?php

namespace App\Modules\Customers\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;

class CustomerAccess
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function canView(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CustomersView);
    }

    public function canCreate(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CustomersCreate);
    }

    public function canUpdate(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CustomersUpdate);
    }

    public function canArchive(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CustomersArchive);
    }
}
