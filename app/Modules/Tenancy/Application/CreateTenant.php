<?php

namespace App\Modules\Tenancy\Application;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Role;
use App\Modules\Inventory\Application\CreateDefaultInventoryLocation;
use App\Modules\Pricing\Application\CreateDefaultPriceList;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use App\Modules\Tenancy\Domain\Enums\TenantStatus;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenant
{
    public function __construct(
        private readonly CreateDefaultPriceList $createDefaultPriceList,
        private readonly CreateDefaultInventoryLocation $createDefaultInventoryLocation,
    ) {}

    public function handle(User $owner, string $name): Tenant
    {
        return DB::transaction(function () use ($owner, $name): Tenant {
            $ownerRole = Role::query()->system(SystemRole::Owner)->sole();

            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'status' => TenantStatus::Active,
            ]);

            TenantMember::create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $owner->getKey(),
                'role_id' => $ownerRole->getKey(),
                'status' => TenantMemberStatus::Active,
                'joined_at' => now(),
            ]);

            $this->createDefaultPriceList->handle($tenant);
            $this->createDefaultInventoryLocation->handle($tenant);

            return $tenant;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';

        return $base.'-'.Str::lower(Str::random(8));
    }
}
