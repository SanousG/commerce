<?php

namespace App\Modules\Pricing\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;

class PricingAccess
{
    public function __construct(private readonly PermissionChecker $permissions) {}

    public function canView(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::PricingView);
    }

    public function canCreate(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::PricingCreate);
    }

    public function canUpdate(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::PricingUpdate);
    }

    public function canArchive(User $user): bool
    {
        return $this->permissions->userCan($user, PermissionKey::PricingArchive);
    }
}
