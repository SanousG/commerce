<?php

namespace Tests\Feature\Access;

use App\Modules\Access\Domain\Enums\PermissionKey;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Access\Domain\Models\Permission;
use App\Modules\Access\Domain\Models\Role;
use Illuminate\Database\QueryException;

class AccessDataTest extends AccessFeatureTestCase
{
    public function test_system_roles_and_permissions_are_bootstrapped(): void
    {
        $this->assertEqualsCanonicalizing(
            array_column(SystemRole::cases(), 'value'),
            Role::query()->system()->pluck('key')->all(),
        );
        $this->assertTrue(Role::query()->system()->get()->every(
            fn (Role $role) => $role->tenant_id === null && $role->is_system,
        ));
        $this->assertEqualsCanonicalizing(
            array_column(PermissionKey::cases(), 'value'),
            Permission::query()->pluck('key')->all(),
        );
    }

    public function test_system_role_permission_matrix_is_explicit(): void
    {
        $all = array_column(PermissionKey::cases(), 'value');
        $admin = array_values(array_diff($all, ['tenant.manage', 'members.manage']));
        $member = [
            'tenant.view',
            'stores.view',
            'stores.select',
            'products.view',
            'categories.view',
            'pricing.view',
            'promotions.view',
            'inventory.view',
            'listings.view',
            'media.view',
            'orders.view',
            'customers.view',
            'carts.view',
            'checkouts.view',
            'fulfillments.view',
            'shipments.view',
            'returns.view',
            'exchanges.view',
            'suppliers.view',
            'procurement.view',
            'fiscal_documents.view',
        ];

        $this->assertEqualsCanonicalizing($all, $this->role(SystemRole::Owner)->permissions()->pluck('key')->all());
        $this->assertEqualsCanonicalizing($admin, $this->role(SystemRole::Admin)->permissions()->pluck('key')->all());
        $this->assertEqualsCanonicalizing($member, $this->role(SystemRole::Member)->permissions()->pluck('key')->all());
    }

    public function test_permission_keys_are_unique(): void
    {
        $this->expectException(QueryException::class);

        Permission::create([
            'key' => PermissionKey::StoresView->value,
            'name' => 'Duplicate',
            'module' => 'stores',
        ]);
    }

    public function test_global_system_role_keys_are_unique(): void
    {
        $this->expectException(QueryException::class);

        Role::create([
            'tenant_id' => null,
            'name' => 'Duplicate owner',
            'key' => SystemRole::Owner->value,
            'is_system' => true,
        ]);
    }

    public function test_role_permission_pairs_are_unique(): void
    {
        $role = $this->role(SystemRole::Member);
        $permission = Permission::query()->where('key', PermissionKey::StoresView)->sole();

        $this->expectException(QueryException::class);
        $role->permissions()->attach($permission);
    }

    public function test_system_role_identity_is_immutable(): void
    {
        $this->expectException(QueryException::class);

        $this->role(SystemRole::Owner)->update([
            'key' => 'FORMER_OWNER',
            'is_system' => false,
            'tenant_id' => $this->createTenant()->getKey(),
        ]);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $this->expectException(QueryException::class);

        $this->role(SystemRole::Member)->delete();
    }
}
