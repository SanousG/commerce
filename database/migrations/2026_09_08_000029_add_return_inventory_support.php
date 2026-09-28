<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT inventory_movements_type_check');
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type IN ('OPENING','ADJUSTMENT_IN','ADJUSTMENT_OUT','TRANSFER_IN','TRANSFER_OUT','RESERVATION_COMMIT','RETURN_RESTOCK'))");
    }

    public function down(): void
    {
        if (DB::table('inventory_movements')->where('type', 'RETURN_RESTOCK')->exists()) {
            throw new RuntimeException('Cannot remove RETURN_RESTOCK while return movements exist.');
        }
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT inventory_movements_type_check');
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type IN ('OPENING','ADJUSTMENT_IN','ADJUSTMENT_OUT','TRANSFER_IN','TRANSFER_OUT','RESERVATION_COMMIT'))");
    }
};
