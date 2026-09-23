<?php

namespace App\Modules\Cart\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;

class CartAccess
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function canView(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CartsView);
    }

    public function canManage(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::CartsManage);
    }
}
