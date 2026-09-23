<?php

namespace App\Modules\Inventory\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;

class InventoryAccess
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function canView(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::InventoryView);
    }

    public function canAdjust(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::InventoryAdjust);
    }
}
