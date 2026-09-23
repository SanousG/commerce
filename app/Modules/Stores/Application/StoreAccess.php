<?php

namespace App\Modules\Stores\Application;

use App\Models\User;
use App\Modules\Access\Application\PermissionChecker;
use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;

class StoreAccess
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker,
        private readonly TenantContext $tenantContext,
    ) {}

    public function canView(User $user, Tenant $tenant): bool
    {
        return $this->forCurrentTenant($tenant)
            && $this->permissionChecker->userCan($user, PermissionKey::StoresView);
    }

    public function canCreate(User $user, Tenant $tenant): bool
    {
        return $this->forCurrentTenant($tenant)
            && $this->permissionChecker->userCan($user, PermissionKey::StoresCreate);
    }

    public function canSelect(User $user, Tenant $tenant): bool
    {
        return $this->forCurrentTenant($tenant)
            && $this->permissionChecker->userCan($user, PermissionKey::StoresSelect);
    }

    private function forCurrentTenant(Tenant $tenant): bool
    {
        return $this->tenantContext->id() === $tenant->getKey();
    }
}
