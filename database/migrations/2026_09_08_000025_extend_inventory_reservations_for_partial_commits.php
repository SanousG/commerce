<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('committed_quantity')->default(0)->after('quantity');
        });
        DB::statement("UPDATE inventory_reservations SET committed_quantity = quantity WHERE status = 'COMMITTED'");
        DB::statement('ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_committed_quantity_check CHECK (committed_quantity >= 0 AND committed_quantity <= quantity)');
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_commit_state_check CHECK ((status = 'COMMITTED') = (committed_quantity = quantity AND committed_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->dropColumn('committed_quantity');
        });
    }
};
