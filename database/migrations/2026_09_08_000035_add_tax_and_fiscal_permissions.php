<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'taxes.view' => '01993000-0000-7000-8000-000000000177',
        'taxes.manage' => '01993000-0000-7000-8000-000000000178',
        'fiscal_documents.view' => '01993000-0000-7000-8000-000000000179',
        'fiscal_documents.issue' => '01993000-0000-7000-8000-000000000180',
        'fiscal_documents.manage' => '01993000-0000-7000-8000-000000000181',
        'fiscal_settings.view' => '01993000-0000-7000-8000-000000000182',
        'fiscal_settings.manage' => '01993000-0000-7000-8000-000000000183',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->upsert(collect(self::PERMISSIONS)->map(fn (string $id, string $key): array => [
            'id' => $id,
            'key' => $key,
            'name' => str($key)->replace('.', ' ')->headline()->toString(),
            'module' => str($key)->before('.')->toString(),
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all(), ['id'], ['key', 'name', 'module', 'updated_at']);

        $roles = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN', 'MEMBER'])->get(['id', 'key']);
        $pivot = [];

        foreach ($roles as $role) {
            $permissions = $role->key === 'MEMBER'
                ? [self::PERMISSIONS['fiscal_documents.view']]
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
