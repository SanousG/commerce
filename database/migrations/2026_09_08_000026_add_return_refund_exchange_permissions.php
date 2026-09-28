<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'returns.view' => '01993000-0000-7000-8000-000000000161',
        'returns.manage' => '01993000-0000-7000-8000-000000000162',
        'returns.inspect' => '01993000-0000-7000-8000-000000000163',
        'returns.restock' => '01993000-0000-7000-8000-000000000164',
        'refunds.view' => '01993000-0000-7000-8000-000000000165',
        'refunds.manage' => '01993000-0000-7000-8000-000000000166',
        'exchanges.view' => '01993000-0000-7000-8000-000000000167',
        'exchanges.manage' => '01993000-0000-7000-8000-000000000168',
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
                ? [self::PERMISSIONS['returns.view'], self::PERMISSIONS['exchanges.view']]
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
