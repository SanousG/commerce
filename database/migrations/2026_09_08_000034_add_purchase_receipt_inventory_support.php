<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT inventory_movements_type_check');
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type IN ('OPENING','ADJUSTMENT_IN','ADJUSTMENT_OUT','TRANSFER_IN','TRANSFER_OUT','RESERVATION_COMMIT','RETURN_RESTOCK','PURCHASE_RECEIPT'))");
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_purchase_receipt_reference_check CHECK (type <> 'PURCHASE_RECEIPT' OR reference_type = 'PURCHASE_RECEIPT_ITEM')");
        DB::statement("CREATE UNIQUE INDEX inventory_movements_purchase_receipt_item_unique ON inventory_movements (tenant_id, reference_id) WHERE type = 'PURCHASE_RECEIPT'");
    }

    public function down(): void
    {
        if (DB::table('inventory_movements')->where('type', 'PURCHASE_RECEIPT')->exists()) {
            throw new RuntimeException('Cannot remove PURCHASE_RECEIPT while purchase receipt movements exist.');
        }
        DB::statement('DROP INDEX IF EXISTS inventory_movements_purchase_receipt_item_unique');
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT inventory_movements_purchase_receipt_reference_check');
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT inventory_movements_type_check');
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type IN ('OPENING','ADJUSTMENT_IN','ADJUSTMENT_OUT','TRANSFER_IN','TRANSFER_OUT','RESERVATION_COMMIT','RETURN_RESTOCK'))");
    }
};
