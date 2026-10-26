<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('admin_locale', 5)->default('es-PE');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_admin_locale_check CHECK (admin_locale IN ('es-PE', 'en'))");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_locale');
        });
    }
};
