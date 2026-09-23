<?php

namespace App\Modules\Promotions\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;

class PromotionAccess
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function canView(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::PromotionsView);
    }

    public function canCreate(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::PromotionsCreate);
    }

    public function canUpdate(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::PromotionsUpdate);
    }

    public function canArchive(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::PromotionsArchive);
    }
}
