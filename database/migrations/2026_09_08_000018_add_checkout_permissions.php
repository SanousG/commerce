<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'checkouts.view' => '01993000-0000-7000-8000-000000000131',
        'checkouts.manage' => '01993000-0000-7000-8000-000000000132',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->upsert(collect(self::PERMISSIONS)->map(fn (string $id, string $key): array => [
            'id' => $id,
            'key' => $key,
            'name' => str($key)->replace('.', ' ')->headline()->toString(),
            'module' => 'checkouts',
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all(), ['id'], ['key', 'name', 'module', 'updated_at']);

        $roles = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN', 'MEMBER'])->pluck('id', 'key');
        $pivot = [];
        foreach (['OWNER', 'ADMIN'] as $role) {
            foreach (self::PERMISSIONS as $permissionId) {
                $pivot[] = ['role_id' => $roles[$role], 'permission_id' => $permissionId];
            }
        }
        $pivot[] = ['role_id' => $roles['MEMBER'], 'permission_id' => self::PERMISSIONS['checkouts.view']];
        DB::table('role_permissions')->insertOrIgnore($pivot);
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission_id', array_values(self::PERMISSIONS))->delete();
        DB::table('permissions')->whereIn('id', array_values(self::PERMISSIONS))->delete();
    }
};
