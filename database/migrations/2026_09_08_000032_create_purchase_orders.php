<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_number_sequences', function (Blueprint $table) {
            $table->uuid('tenant_id')->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE purchase_order_number_sequences ADD CONSTRAINT purchase_order_sequences_next_check CHECK (next_number > 0)');

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('supplier_id');
            $table->string('number', 30);
            $table->string('status', 24);
            $table->char('currency', 3);
            $table->timestampTz('expected_at')->nullable();
            $table->timestampTz('ordered_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('discount_total', 19, 4)->default(0);
            $table->decimal('shipping_total', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('grand_total', 19, 4)->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'supplier_id', 'tenant_id']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'supplier_id', 'created_at']);
            $table->foreign(['supplier_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('suppliers')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('DRAFT','APPROVED','ORDERED','PARTIALLY_RECEIVED','RECEIVED','CANCELLED'))");
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_totals_check CHECK (subtotal >= 0 AND discount_total >= 0 AND discount_total <= subtotal AND shipping_total >= 0 AND tax_total >= 0 AND grand_total = subtotal - discount_total + shipping_total + tax_total)');
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_lifecycle_check CHECK ((status = 'DRAFT' AND approved_by_user_id IS NULL AND ordered_at IS NULL AND cancelled_at IS NULL AND completed_at IS NULL) OR (status = 'APPROVED' AND approved_by_user_id IS NOT NULL AND ordered_at IS NULL AND cancelled_at IS NULL AND completed_at IS NULL) OR (status IN ('ORDERED','PARTIALLY_RECEIVED') AND approved_by_user_id IS NOT NULL AND ordered_at IS NOT NULL AND cancelled_at IS NULL AND completed_at IS NULL) OR (status = 'RECEIVED' AND approved_by_user_id IS NOT NULL AND ordered_at IS NOT NULL AND cancelled_at IS NULL AND completed_at IS NOT NULL) OR (status = 'CANCELLED' AND cancelled_at IS NOT NULL AND completed_at IS NULL))");
        DB::statement("CREATE INDEX purchase_orders_open_index ON purchase_orders (tenant_id, expected_at) WHERE status IN ('ORDERED','PARTIALLY_RECEIVED')");

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('purchase_order_id');
            $table->uuid('supplier_product_id');
            $table->uuid('product_id');
            $table->uuid('variant_id');
            $table->string('supplier_sku', 100)->nullable();
            $table->string('product_name', 160);
            $table->string('variant_name', 160)->nullable();
            $table->string('sku', 120);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('quantity_ordered');
            $table->decimal('unit_cost', 19, 4);
            $table->char('currency', 3);
            $table->decimal('line_total', 19, 4);
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'purchase_order_id', 'variant_id', 'tenant_id'], 'purchase_order_items_variant_tenant_unique');
            $table->unique(['purchase_order_id', 'supplier_product_id']);
            $table->index(['tenant_id', 'variant_id']);
            $table->foreign(['purchase_order_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('purchase_orders')->cascadeOnDelete();
            $table->foreign(['supplier_product_id', 'variant_id', 'tenant_id'], 'purchase_order_items_supplier_product_fk')
                ->references(['id', 'product_variant_id', 'tenant_id'])->on('supplier_products')->restrictOnDelete();
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->restrictOnDelete();
            $table->foreign(['variant_id', 'product_id', 'tenant_id'])
                ->references(['id', 'product_id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_snapshot_check CHECK (btrim(product_name) <> '' AND btrim(sku) <> '' AND (supplier_sku IS NULL OR btrim(supplier_sku) <> ''))");
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_values_check CHECK (quantity_ordered > 0 AND unit_cost >= 0 AND line_total = quantity_ordered * unit_cost)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_purchase_order_commercial_data() RETURNS trigger AS $$
            BEGIN
                IF TG_TABLE_NAME = 'purchase_orders' THEN
                    IF TG_OP = 'DELETE' AND OLD.status <> 'DRAFT' THEN
                        RAISE EXCEPTION 'Non-draft purchase orders cannot be deleted';
                    ELSIF TG_OP = 'UPDATE' AND OLD.status <> 'DRAFT' AND
                       (NEW.supplier_id, NEW.currency, NEW.expected_at, NEW.notes, NEW.subtotal, NEW.discount_total, NEW.shipping_total, NEW.tax_total, NEW.grand_total)
                       IS DISTINCT FROM
                       (OLD.supplier_id, OLD.currency, OLD.expected_at, OLD.notes, OLD.subtotal, OLD.discount_total, OLD.shipping_total, OLD.tax_total, OLD.grand_total) THEN
                        RAISE EXCEPTION 'Approved purchase order commercial data is immutable';
                    END IF;
                ELSIF TG_TABLE_NAME = 'purchase_order_items' AND
                      TG_OP <> 'DELETE' AND NOT EXISTS (
                          SELECT 1
                          FROM purchase_orders po
                          JOIN supplier_products sp ON sp.id = NEW.supplier_product_id
                          WHERE po.id = NEW.purchase_order_id
                            AND po.tenant_id = NEW.tenant_id
                            AND sp.tenant_id = NEW.tenant_id
                            AND sp.product_variant_id = NEW.variant_id
                            AND po.supplier_id = sp.supplier_id
                            AND po.currency = NEW.currency
                      ) THEN
                    RAISE EXCEPTION 'Purchase order item supplier, variant, currency, or tenant is incoherent';
                ELSIF TG_TABLE_NAME = 'purchase_order_items' AND
                      (SELECT status FROM purchase_orders WHERE id = COALESCE(NEW.purchase_order_id, OLD.purchase_order_id)) <> 'DRAFT' THEN
                    RAISE EXCEPTION 'Items of a non-draft purchase order are immutable';
                END IF;
                RETURN COALESCE(NEW, OLD);
            END; $$ LANGUAGE plpgsql;
            CREATE TRIGGER purchase_orders_commercial_guard BEFORE UPDATE OR DELETE ON purchase_orders FOR EACH ROW EXECUTE FUNCTION protect_purchase_order_commercial_data();
            CREATE TRIGGER purchase_order_items_commercial_guard BEFORE INSERT OR UPDATE OR DELETE ON purchase_order_items FOR EACH ROW EXECUTE FUNCTION protect_purchase_order_commercial_data();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS purchase_order_items_commercial_guard ON purchase_order_items');
        DB::statement('DROP TRIGGER IF EXISTS purchase_orders_commercial_guard ON purchase_orders');
        DB::statement('DROP FUNCTION IF EXISTS protect_purchase_order_commercial_data()');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_order_number_sequences');
    }
};
