<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION_ID = '01993000-0000-7000-8000-000000000186';

    public function up(): void
    {
        $now = now();
        DB::table('permissions')->upsert([[
            'id' => self::PERMISSION_ID,
            'key' => 'audit.view',
            'name' => 'Audit View',
            'module' => 'audit',
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['id'], ['key', 'name', 'module', 'updated_at']);

        $pivots = DB::table('roles')->whereNull('tenant_id')->where('is_system', true)
            ->whereIn('key', ['OWNER', 'ADMIN'])->get(['id'])->map(fn ($role): array => [
                'role_id' => $role->id,
                'permission_id' => self::PERMISSION_ID,
            ])->all();

        DB::table('role_permissions')->insertOrIgnore($pivots);
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission_id', self::PERMISSION_ID)->delete();
        DB::table('permissions')->where('id', self::PERMISSION_ID)->delete();
    }
};
