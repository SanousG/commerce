<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->string('status', 20);
            $table->string('reason', 500)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('inspection_started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'order_id', 'store_id', 'tenant_id'], 'returns_order_store_tenant_unique');
            $table->index(['tenant_id', 'store_id', 'requested_at']);
            $table->index(['order_id', 'status']);
            $table->foreign(['order_id', 'store_id', 'tenant_id'])->references(['id', 'store_id', 'tenant_id'])->on('orders')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE returns ADD CONSTRAINT returns_status_check CHECK (status IN ('REQUESTED','APPROVED','REJECTED','RECEIVED','INSPECTING','COMPLETED','CANCELLED'))");
        DB::statement("ALTER TABLE returns ADD CONSTRAINT returns_timestamps_check CHECK ((status = 'REQUESTED' AND approved_at IS NULL AND rejected_at IS NULL AND received_at IS NULL AND inspection_started_at IS NULL AND completed_at IS NULL AND cancelled_at IS NULL) OR (status = 'APPROVED' AND approved_at IS NOT NULL AND rejected_at IS NULL AND received_at IS NULL AND inspection_started_at IS NULL AND completed_at IS NULL AND cancelled_at IS NULL) OR (status = 'REJECTED' AND rejected_at IS NOT NULL AND rejection_reason IS NOT NULL AND completed_at IS NULL AND cancelled_at IS NULL) OR (status = 'RECEIVED' AND approved_at IS NOT NULL AND received_at IS NOT NULL AND inspection_started_at IS NULL AND completed_at IS NULL AND cancelled_at IS NULL) OR (status = 'INSPECTING' AND approved_at IS NOT NULL AND received_at IS NOT NULL AND inspection_started_at IS NOT NULL AND completed_at IS NULL AND cancelled_at IS NULL) OR (status = 'COMPLETED' AND approved_at IS NOT NULL AND received_at IS NOT NULL AND inspection_started_at IS NOT NULL AND completed_at IS NOT NULL AND cancelled_at IS NULL) OR (status = 'CANCELLED' AND cancelled_at IS NOT NULL AND cancellation_reason IS NOT NULL AND completed_at IS NULL))");

        Schema::create('return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('return_id');
            $table->uuid('order_id');
            $table->uuid('order_item_id');
            $table->uuid('product_variant_id');
            $table->unsignedBigInteger('requested_quantity');
            $table->unsignedBigInteger('received_quantity')->default(0);
            $table->unsignedBigInteger('accepted_quantity')->default(0);
            $table->unsignedBigInteger('restocked_quantity')->default(0);
            $table->string('reason', 500)->nullable();
            $table->string('condition', 20)->nullable();
            $table->string('disposition', 24)->nullable();
            $table->text('inspection_notes')->nullable();
            $table->foreignId('inspected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('inspected_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'store_id', 'tenant_id'], 'return_items_store_tenant_unique');
            $table->unique(['id', 'return_id', 'store_id', 'tenant_id'], 'return_items_parent_unique');
            $table->unique(['return_id', 'order_item_id']);
            $table->index(['tenant_id', 'order_item_id']);
            $table->foreign(['return_id', 'order_id', 'store_id', 'tenant_id'])->references(['id', 'order_id', 'store_id', 'tenant_id'])->on('returns')->restrictOnDelete();
            $table->foreign(['order_item_id', 'order_id', 'store_id', 'product_variant_id', 'tenant_id'], 'return_items_order_item_fk')
                ->references(['id', 'order_id', 'store_id', 'product_variant_id', 'tenant_id'])->on('order_items')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE return_items ADD CONSTRAINT return_items_quantities_check CHECK (requested_quantity > 0 AND requested_quantity <= 9999 AND received_quantity <= requested_quantity AND accepted_quantity <= received_quantity AND restocked_quantity <= accepted_quantity)');
        DB::statement("ALTER TABLE return_items ADD CONSTRAINT return_items_condition_check CHECK (condition IS NULL OR condition IN ('SELLABLE','DAMAGED','DEFECTIVE','OPEN_BOX','UNKNOWN'))");
        DB::statement("ALTER TABLE return_items ADD CONSTRAINT return_items_disposition_check CHECK (disposition IS NULL OR disposition IN ('RESTOCK','SCRAP','QUARANTINE','RETURN_TO_VENDOR','NO_ACTION'))");
        DB::statement('ALTER TABLE return_items ADD CONSTRAINT return_items_inspection_check CHECK ((condition IS NULL AND disposition IS NULL AND inspected_at IS NULL) OR (condition IS NOT NULL AND disposition IS NOT NULL AND inspected_at IS NOT NULL))');

        Schema::create('return_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('return_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['return_id', 'created_at']);
            $table->foreign(['return_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('returns')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE return_status_histories ADD CONSTRAINT return_histories_from_check CHECK (from_status IS NULL OR from_status IN ('REQUESTED','APPROVED','REJECTED','RECEIVED','INSPECTING','COMPLETED','CANCELLED'))");
        DB::statement("ALTER TABLE return_status_histories ADD CONSTRAINT return_histories_to_check CHECK (to_status IN ('REQUESTED','APPROVED','REJECTED','RECEIVED','INSPECTING','COMPLETED','CANCELLED'))");
        DB::statement('ALTER TABLE return_status_histories ADD CONSTRAINT return_histories_transition_check CHECK (from_status IS NULL OR from_status <> to_status)');

        Schema::create('exchanges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->uuid('return_id');
            $table->string('status', 20);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'store_id', 'tenant_id'], 'exchanges_store_tenant_unique');
            $table->unique(['id', 'return_id', 'store_id', 'tenant_id'], 'exchanges_parent_unique');
            $table->index(['tenant_id', 'store_id', 'created_at']);
            $table->index(['return_id', 'status']);
            $table->foreign(['return_id', 'order_id', 'store_id', 'tenant_id'])->references(['id', 'order_id', 'store_id', 'tenant_id'])->on('returns')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE exchanges ADD CONSTRAINT exchanges_status_check CHECK (status IN ('PENDING','APPROVED','COMPLETED','CANCELLED'))");
        DB::statement("ALTER TABLE exchanges ADD CONSTRAINT exchanges_timestamps_check CHECK ((status = 'PENDING' AND approved_at IS NULL AND completed_at IS NULL AND cancelled_at IS NULL) OR (status = 'APPROVED' AND approved_at IS NOT NULL AND completed_at IS NULL AND cancelled_at IS NULL) OR (status = 'COMPLETED' AND approved_at IS NOT NULL AND completed_at IS NOT NULL AND cancelled_at IS NULL) OR (status = 'CANCELLED' AND completed_at IS NULL AND cancelled_at IS NOT NULL AND cancellation_reason IS NOT NULL))");

        Schema::create('exchange_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('exchange_id');
            $table->uuid('return_id');
            $table->uuid('return_item_id');
            $table->uuid('replacement_product_variant_id');
            $table->unsignedBigInteger('quantity');
            $table->timestamps();
            $table->unique(['exchange_id', 'return_item_id']);
            $table->index(['tenant_id', 'return_item_id']);
            $table->foreign(['exchange_id', 'return_id', 'store_id', 'tenant_id'])->references(['id', 'return_id', 'store_id', 'tenant_id'])->on('exchanges')->restrictOnDelete();
            $table->foreign(['return_item_id', 'return_id', 'store_id', 'tenant_id'])->references(['id', 'return_id', 'store_id', 'tenant_id'])->on('return_items')->restrictOnDelete();
            $table->foreign(['replacement_product_variant_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE exchange_items ADD CONSTRAINT exchange_items_quantity_check CHECK (quantity > 0 AND quantity <= 9999)');

        DB::unprepared("CREATE OR REPLACE FUNCTION prevent_return_status_histories_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'return_status_histories is append-only'; END; $$ LANGUAGE plpgsql; CREATE TRIGGER return_status_histories_append_only BEFORE UPDATE OR DELETE ON return_status_histories FOR EACH ROW EXECUTE FUNCTION prevent_return_status_histories_mutation();");
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_items');
        Schema::dropIfExists('exchanges');
        Schema::dropIfExists('return_status_histories');
        DB::statement('DROP FUNCTION IF EXISTS prevent_return_status_histories_mutation()');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
