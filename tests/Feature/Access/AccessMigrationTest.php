<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Domain\Enums\SystemRole;
use App\Modules\Tenancy\Domain\Models\TenantMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccessMigrationTest extends AccessFeatureTestCase
{
    public function test_legacy_roles_round_trip_through_role_id_migration(): void
    {
        foreach (SystemRole::cases() as $role) {
            $this->createMembership(User::factory()->create(), $this->createTenant(), $role);
        }

        $migration = require database_path('migrations/2026_09_08_000004_create_access_control_tables.php');
        $invitationMigration = require database_path('migrations/2026_09_08_000046_create_tenant_invitations.php');
        $this->assertInstanceOf(Migration::class, $migration);

        $invitationMigration->down();
        $migration->down();

        $this->assertTrue(Schema::hasColumn('tenant_members', 'role'));
        $this->assertFalse(Schema::hasColumn('tenant_members', 'role_id'));
        $this->assertEqualsCanonicalizing(
            array_column(SystemRole::cases(), 'value'),
            DB::table('tenant_members')->pluck('role')->all(),
        );

        $migration->up();
        $invitationMigration->up();

        $this->assertFalse(Schema::hasColumn('tenant_members', 'role'));
        $this->assertTrue(Schema::hasColumn('tenant_members', 'role_id'));
        $this->assertEqualsCanonicalizing(
            array_column(SystemRole::cases(), 'value'),
            TenantMember::query()->with('role')->get()->pluck('role.key')->all(),
        );
    }
}
