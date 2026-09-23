<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'carts.view' => '01993000-0000-7000-8000-000000000129',
        'carts.manage' => '01993000-0000-7000-8000-000000000130',
    ];

    public function up(): void
    {
        $now = now();
        $permissions = collect(self::PERMISSIONS)->map(fn (string $id, string $key): array => [
            'id' => $id,
            'key' => $key,
            'name' => str($key)->replace('.', ' ')->headline()->toString(),
            'module' => 'carts',
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();
        DB::table('permissions')->upsert($permissions, ['id'], ['key', 'name', 'module', 'updated_at']);

        $roleIds = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN', 'MEMBER'])->pluck('id', 'key');
        $pivot = [];
        foreach (['OWNER', 'ADMIN'] as $role) {
            foreach (self::PERMISSIONS as $permissionId) {
                $pivot[] = ['role_id' => $roleIds[$role], 'permission_id' => $permissionId];
            }
        }
        $pivot[] = ['role_id' => $roleIds['MEMBER'], 'permission_id' => self::PERMISSIONS['carts.view']];
        DB::table('role_permissions')->insertOrIgnore($pivot);
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission_id', array_values(self::PERMISSIONS))->delete();
        DB::table('permissions')->whereIn('id', array_values(self::PERMISSIONS))->delete();
    }
};
