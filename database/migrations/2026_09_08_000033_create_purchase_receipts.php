<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('purchase_order_id');
            $table->uuid('supplier_id');
            $table->uuid('inventory_location_id');
            $table->string('status', 16);
            $table->timestampTz('received_at');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'purchase_order_id', 'tenant_id']);
            $table->index(['tenant_id', 'purchase_order_id', 'status']);
            $table->foreign(['purchase_order_id', 'supplier_id', 'tenant_id'])
                ->references(['id', 'supplier_id', 'tenant_id'])->on('purchase_orders')->restrictOnDelete();
            $table->foreign(['inventory_location_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE purchase_receipts ADD CONSTRAINT purchase_receipts_status_check CHECK (status IN ('DRAFT','POSTED','CANCELLED'))");
        DB::statement('CREATE UNIQUE INDEX purchase_receipts_reference_unique ON purchase_receipts (purchase_order_id, reference) WHERE reference IS NOT NULL');

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('purchase_receipt_id');
            $table->uuid('purchase_order_id');
            $table->uuid('purchase_order_item_id');
            $table->uuid('variant_id');
            $table->unsignedBigInteger('quantity');
            $table->decimal('unit_cost', 19, 4);
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['purchase_receipt_id', 'purchase_order_item_id']);
            $table->index(['tenant_id', 'purchase_order_item_id']);
            $table->foreign(['purchase_receipt_id', 'purchase_order_id', 'tenant_id'])->references(['id', 'purchase_order_id', 'tenant_id'])->on('purchase_receipts')->cascadeOnDelete();
            $table->foreign(['purchase_order_item_id', 'purchase_order_id', 'variant_id', 'tenant_id'], 'purchase_receipt_items_order_item_fk')
                ->references(['id', 'purchase_order_id', 'variant_id', 'tenant_id'])->on('purchase_order_items')->restrictOnDelete();
            $table->foreign(['variant_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE purchase_receipt_items ADD CONSTRAINT purchase_receipt_items_values_check CHECK (quantity > 0 AND unit_cost >= 0)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_purchase_receipt_contents() RETURNS trigger AS $$
            BEGIN
                IF TG_TABLE_NAME = 'purchase_receipts' THEN
                    IF TG_OP = 'DELETE' AND OLD.status <> 'DRAFT' THEN
                        RAISE EXCEPTION 'Posted or cancelled purchase receipts cannot be deleted';
                    ELSIF TG_OP = 'UPDATE' AND OLD.status <> 'DRAFT' AND
                       (NEW.purchase_order_id, NEW.inventory_location_id, NEW.received_at, NEW.reference, NEW.notes, NEW.received_by_user_id)
                       IS DISTINCT FROM (OLD.purchase_order_id, OLD.inventory_location_id, OLD.received_at, OLD.reference, OLD.notes, OLD.received_by_user_id) THEN
                        RAISE EXCEPTION 'Posted or cancelled purchase receipt contents are immutable';
                    END IF;
                ELSIF TG_TABLE_NAME = 'purchase_receipt_items' AND
                      (SELECT status FROM purchase_receipts WHERE id = COALESCE(NEW.purchase_receipt_id, OLD.purchase_receipt_id)) <> 'DRAFT' THEN
                    RAISE EXCEPTION 'Items of a posted or cancelled purchase receipt are immutable';
                END IF;
                RETURN COALESCE(NEW, OLD);
            END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER purchase_receipts_content_guard BEFORE UPDATE OR DELETE ON purchase_receipts FOR EACH ROW EXECUTE FUNCTION protect_purchase_receipt_contents();
            CREATE TRIGGER purchase_receipt_items_content_guard BEFORE INSERT OR UPDATE OR DELETE ON purchase_receipt_items FOR EACH ROW EXECUTE FUNCTION protect_purchase_receipt_contents();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS purchase_receipt_items_content_guard ON purchase_receipt_items');
        DB::statement('DROP TRIGGER IF EXISTS purchase_receipts_content_guard ON purchase_receipts');
        DB::statement('DROP FUNCTION IF EXISTS protect_purchase_receipt_contents()');
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};
