<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'media.view' => '01993000-0000-7000-8000-000000000187',
        'media.upload' => '01993000-0000-7000-8000-000000000188',
        'media.manage' => '01993000-0000-7000-8000-000000000189',
        'media.delete' => '01993000-0000-7000-8000-00000000018a',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->upsert(collect(self::PERMISSIONS)->map(fn (string $id, string $key): array => [
            'id' => $id, 'key' => $key, 'name' => str($key)->replace('.', ' ')->headline()->toString(),
            'module' => 'media', 'created_at' => $now, 'updated_at' => $now,
        ])->values()->all(), ['id'], ['key', 'name', 'module', 'updated_at']);

        $roles = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN', 'MEMBER'])->get(['id', 'key']);
        $pivots = [];
        foreach ($roles as $role) {
            $permissionIds = $role->key === 'MEMBER'
                ? [self::PERMISSIONS['media.view']]
                : array_values(self::PERMISSIONS);
            foreach ($permissionIds as $permissionId) {
                $pivots[] = ['role_id' => $role->id, 'permission_id' => $permissionId];
            }
        }
        DB::table('role_permissions')->insertOrIgnore($pivots);
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission_id', array_values(self::PERMISSIONS))->delete();
        DB::table('permissions')->whereIn('id', array_values(self::PERMISSIONS))->delete();
    }
};
