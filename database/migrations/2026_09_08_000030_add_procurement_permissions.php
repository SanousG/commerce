<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'suppliers.view' => '01993000-0000-7000-8000-000000000169',
        'suppliers.create' => '01993000-0000-7000-8000-000000000170',
        'suppliers.update' => '01993000-0000-7000-8000-000000000171',
        'suppliers.archive' => '01993000-0000-7000-8000-000000000172',
        'procurement.view' => '01993000-0000-7000-8000-000000000173',
        'procurement.manage' => '01993000-0000-7000-8000-000000000174',
        'procurement.approve' => '01993000-0000-7000-8000-000000000175',
        'procurement.receive' => '01993000-0000-7000-8000-000000000176',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->upsert(collect(self::PERMISSIONS)->map(fn (string $id, string $key): array => [
            'id' => $id, 'key' => $key, 'name' => str($key)->replace('.', ' ')->headline()->toString(),
            'module' => str($key)->before('.')->toString(), 'created_at' => $now, 'updated_at' => $now,
        ])->values()->all(), ['id'], ['key', 'name', 'module', 'updated_at']);

        $roles = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN', 'MEMBER'])->get(['id', 'key']);
        $pivot = [];
        foreach ($roles as $role) {
            $permissions = $role->key === 'MEMBER'
                ? [self::PERMISSIONS['suppliers.view'], self::PERMISSIONS['procurement.view']]
                : array_values(self::PERMISSIONS);
            foreach ($permissions as $permissionId) {
                $pivot[] = ['role_id' => $role->id, 'permission_id' => $permissionId];
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
