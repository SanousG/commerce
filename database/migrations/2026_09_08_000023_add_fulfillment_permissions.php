<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'fulfillments.view' => '01993000-0000-7000-8000-000000000151',
        'fulfillments.manage' => '01993000-0000-7000-8000-000000000152',
        'fulfillments.complete' => '01993000-0000-7000-8000-000000000153',
        'shipments.view' => '01993000-0000-7000-8000-000000000154',
        'shipments.manage' => '01993000-0000-7000-8000-000000000155',
    ];

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->upsert(collect(self::PERMISSIONS)->map(fn (string $id, string $key): array => [
            'id' => $id,
            'key' => $key,
            'name' => str($key)->replace('.', ' ')->headline()->toString(),
            'module' => 'fulfillment',
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all(), ['id'], ['key', 'name', 'module', 'updated_at']);

        $pivot = [];
        $roles = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN', 'MEMBER'])->get(['id', 'key']);
        foreach ($roles as $role) {
            $permissions = $role->key === 'MEMBER'
                ? [self::PERMISSIONS['fulfillments.view'], self::PERMISSIONS['shipments.view']]
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
