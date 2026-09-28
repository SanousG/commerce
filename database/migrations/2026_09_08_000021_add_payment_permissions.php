<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'payments.view' => '01993000-0000-7000-8000-000000000141',
        'payments.manage' => '01993000-0000-7000-8000-000000000142',
        'payments.capture' => '01993000-0000-7000-8000-000000000143',
        'payments.settings' => '01993000-0000-7000-8000-000000000144',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->upsert(collect(self::PERMISSIONS)->map(fn (string $id, string $key): array => [
            'id' => $id,
            'key' => $key,
            'name' => str($key)->replace('.', ' ')->headline()->toString(),
            'module' => 'payments',
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all(), ['id'], ['key', 'name', 'module', 'updated_at']);

        $roles = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN'])->pluck('id', 'key');
        $pivot = [];
        foreach ($roles as $roleId) {
            foreach (self::PERMISSIONS as $permissionId) {
                $pivot[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
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
