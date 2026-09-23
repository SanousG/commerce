<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'customers.create' => '01993000-0000-7000-8000-000000000126',
        'customers.update' => '01993000-0000-7000-8000-000000000127',
        'customers.archive' => '01993000-0000-7000-8000-000000000128',
    ];

    public function up(): void
    {
        $now = now();
        $permissions = collect(self::PERMISSIONS)->map(fn (string $id, string $key): array => [
            'id' => $id,
            'key' => $key,
            'name' => str($key)->replace('.', ' ')->headline()->toString(),
            'module' => 'customers',
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();
        DB::table('permissions')->upsert($permissions, ['id'], ['key', 'name', 'module', 'updated_at']);

        $roleIds = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN'])->pluck('id', 'key');
        $pivot = [];
        foreach (['OWNER', 'ADMIN'] as $role) {
            foreach (self::PERMISSIONS as $permissionId) {
                $pivot[] = ['role_id' => $roleIds[$role], 'permission_id' => $permissionId];
            }
        }
        DB::table('role_permissions')->insertOrIgnore($pivot);
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission_id', array_values(self::PERMISSIONS))->delete();
        DB::table('permissions')->whereIn('id', array_values(self::PERMISSIONS))->delete();
    }
};
