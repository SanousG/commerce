<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'inventory.cost.view' => '01993000-0000-7000-8000-000000000184',
        'inventory.cost.manage' => '01993000-0000-7000-8000-000000000185',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->upsert(
            collect(self::PERMISSIONS)->map(fn (string $id, string $key): array => [
                'id' => $id,
                'key' => $key,
                'name' => str($key)->replace('.', ' ')->headline()->toString(),
                'module' => 'inventory',
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all(),
            ['id'],
            ['key', 'name', 'module', 'updated_at'],
        );

        $permissionIds = array_values(self::PERMISSIONS);
        $pivots = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN'])->get(['id'])
            ->flatMap(fn ($role) => collect($permissionIds)->map(fn (string $permissionId): array => [
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ]))->all();
        DB::table('role_permissions')->insertOrIgnore($pivots);
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission_id', array_values(self::PERMISSIONS))->delete();
        DB::table('permissions')->whereIn('id', array_values(self::PERMISSIONS))->delete();
    }
};
