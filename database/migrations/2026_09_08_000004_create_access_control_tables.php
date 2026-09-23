<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const ROLE_IDS = [
        'OWNER' => '01993000-0000-7000-8000-000000000001',
        'ADMIN' => '01993000-0000-7000-8000-000000000002',
        'MEMBER' => '01993000-0000-7000-8000-000000000003',
    ];

    /** @var array<string, string> */
    private const PERMISSION_IDS = [
        'tenant.view' => '01993000-0000-7000-8000-000000000101',
        'tenant.manage' => '01993000-0000-7000-8000-000000000102',
        'stores.view' => '01993000-0000-7000-8000-000000000103',
        'stores.create' => '01993000-0000-7000-8000-000000000104',
        'stores.update' => '01993000-0000-7000-8000-000000000105',
        'stores.select' => '01993000-0000-7000-8000-000000000106',
        'members.view' => '01993000-0000-7000-8000-000000000107',
        'members.manage' => '01993000-0000-7000-8000-000000000108',
        'products.view' => '01993000-0000-7000-8000-000000000109',
        'products.create' => '01993000-0000-7000-8000-00000000010a',
        'products.update' => '01993000-0000-7000-8000-00000000010b',
        'products.archive' => '01993000-0000-7000-8000-00000000010c',
        'inventory.view' => '01993000-0000-7000-8000-00000000010d',
        'inventory.adjust' => '01993000-0000-7000-8000-00000000010e',
        'orders.view' => '01993000-0000-7000-8000-00000000010f',
        'orders.manage' => '01993000-0000-7000-8000-000000000110',
        'customers.view' => '01993000-0000-7000-8000-000000000111',
        'analytics.view' => '01993000-0000-7000-8000-000000000112',
        'settings.view' => '01993000-0000-7000-8000-000000000113',
        'settings.manage' => '01993000-0000-7000-8000-000000000114',
    ];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('key', 80);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX roles_system_key_unique ON roles (key) WHERE tenant_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX roles_tenant_key_unique ON roles (tenant_id, key) WHERE tenant_id IS NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE roles
            ADD CONSTRAINT roles_scope_check CHECK (
                (is_system AND tenant_id IS NULL AND key IN ('OWNER', 'ADMIN', 'MEMBER'))
                OR (NOT is_system AND tenant_id IS NOT NULL)
            )
        SQL);

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();
            $table->string('name', 120);
            $table->string('module', 60)->index();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        $this->seedAccessData();

        Schema::table('tenant_members', function (Blueprint $table) {
            $table->uuid('role_id')->nullable()->index();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE tenant_members AS membership
            SET role_id = roles.id
            FROM roles
            WHERE roles.tenant_id IS NULL
              AND roles.is_system = true
              AND roles.key = membership.role
        SQL);

        if (DB::table('tenant_members')->whereNull('role_id')->exists()) {
            throw new RuntimeException('Every tenant membership must map to a system role.');
        }

        DB::statement('ALTER TABLE tenant_members ALTER COLUMN role_id SET NOT NULL');
        DB::statement('DROP FUNCTION IF EXISTS enforce_tenant_member_system_role() CASCADE');
        DB::statement('DROP FUNCTION IF EXISTS protect_system_role_identity() CASCADE');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION enforce_tenant_member_system_role() RETURNS trigger AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM roles
                    WHERE id = NEW.role_id
                      AND tenant_id IS NULL
                      AND is_system = true
                ) THEN
                    RAISE EXCEPTION 'Tenant memberships require a system role' USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER tenant_members_system_role_check
            BEFORE INSERT OR UPDATE OF role_id ON tenant_members
            FOR EACH ROW EXECUTE FUNCTION enforce_tenant_member_system_role()
        SQL);
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION protect_system_role_identity() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' AND OLD.is_system THEN
                    RAISE EXCEPTION 'System roles cannot be deleted' USING ERRCODE = '23514';
                END IF;

                IF OLD.is_system AND (
                    NEW.key IS DISTINCT FROM OLD.key
                    OR NEW.tenant_id IS DISTINCT FROM OLD.tenant_id
                    OR NEW.is_system IS DISTINCT FROM OLD.is_system
                ) THEN
                    RAISE EXCEPTION 'System role identity is immutable' USING ERRCODE = '23514';
                END IF;

                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER roles_system_identity_check
            BEFORE UPDATE OF key, tenant_id, is_system OR DELETE ON roles
            FOR EACH ROW EXECUTE FUNCTION protect_system_role_identity()
        SQL);

        Schema::table('tenant_members', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_members', function (Blueprint $table) {
            $table->enum('role', ['OWNER', 'ADMIN', 'MEMBER'])->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE tenant_members AS membership
            SET role = roles.key
            FROM roles
            WHERE roles.id = membership.role_id
              AND roles.tenant_id IS NULL
              AND roles.key IN ('OWNER', 'ADMIN', 'MEMBER')
        SQL);

        if (DB::table('tenant_members')->whereNull('role')->exists()) {
            throw new RuntimeException('Tenant memberships with custom roles cannot be rolled back.');
        }

        DB::statement('ALTER TABLE tenant_members ALTER COLUMN role SET NOT NULL');
        DB::statement('DROP TRIGGER tenant_members_system_role_check ON tenant_members');
        DB::statement('DROP FUNCTION enforce_tenant_member_system_role()');
        DB::statement('DROP TRIGGER roles_system_identity_check ON roles');
        DB::statement('DROP FUNCTION protect_system_role_identity()');

        Schema::table('tenant_members', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }

    private function seedAccessData(): void
    {
        $now = now();
        $roles = [
            ['id' => self::ROLE_IDS['OWNER'], 'tenant_id' => null, 'name' => 'Owner', 'key' => 'OWNER', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::ROLE_IDS['ADMIN'], 'tenant_id' => null, 'name' => 'Administrator', 'key' => 'ADMIN', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::ROLE_IDS['MEMBER'], 'tenant_id' => null, 'name' => 'Member', 'key' => 'MEMBER', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('roles')->upsert($roles, ['id'], ['name', 'key', 'is_system', 'updated_at']);

        $permissions = collect(self::PERMISSION_IDS)->map(function (string $id, string $key) use ($now): array {
            $module = explode('.', $key, 2)[0];

            return [
                'id' => $id,
                'key' => $key,
                'name' => str($key)->replace('.', ' ')->headline()->toString(),
                'module' => $module,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values()->all();

        DB::table('permissions')->upsert($permissions, ['id'], ['key', 'name', 'module', 'updated_at']);

        $adminPermissions = array_diff(array_keys(self::PERMISSION_IDS), ['tenant.manage', 'members.manage']);
        $memberPermissions = [
            'tenant.view',
            'stores.view',
            'stores.select',
            'products.view',
            'inventory.view',
            'orders.view',
            'customers.view',
        ];

        $matrix = [
            'OWNER' => array_keys(self::PERMISSION_IDS),
            'ADMIN' => $adminPermissions,
            'MEMBER' => $memberPermissions,
        ];

        $pivot = [];
        foreach ($matrix as $role => $permissionKeys) {
            foreach ($permissionKeys as $permissionKey) {
                $pivot[] = [
                    'role_id' => self::ROLE_IDS[$role],
                    'permission_id' => self::PERMISSION_IDS[$permissionKey],
                ];
            }
        }

        DB::table('role_permissions')->insertOrIgnore($pivot);
    }
};
