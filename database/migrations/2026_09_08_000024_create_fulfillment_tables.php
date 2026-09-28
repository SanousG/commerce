<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unique(
                ['id', 'order_id', 'store_id', 'product_variant_id', 'tenant_id'],
                'order_items_fulfillment_reference_unique',
            );
        });
        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->unique(
                ['id', 'inventory_location_id', 'product_variant_id', 'tenant_id'],
                'inventory_reservations_fulfillment_reference_unique',
            );
        });

        Schema::create('fulfillments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->uuid('inventory_location_id');
            $table->string('status', 20);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'order_id', 'store_id', 'tenant_id'], 'fulfillments_id_order_store_tenant_unique');
            $table->unique(
                ['id', 'order_id', 'store_id', 'inventory_location_id', 'tenant_id'],
                'fulfillments_item_reference_unique',
            );
            $table->index(['tenant_id', 'store_id', 'created_at']);
            $table->index(['order_id', 'status']);
            $table->index(['inventory_location_id', 'status']);
            $table->foreign(['order_id', 'store_id', 'tenant_id'])
                ->references(['id', 'store_id', 'tenant_id'])->on('orders')->restrictOnDelete();
            $table->foreign(['inventory_location_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE fulfillments ADD CONSTRAINT fulfillments_status_check CHECK (status IN ('PENDING', 'PROCESSING', 'FULFILLED', 'CANCELLED'))");
        DB::statement("ALTER TABLE fulfillments ADD CONSTRAINT fulfillments_timestamps_check CHECK ((status = 'PENDING' AND started_at IS NULL AND fulfilled_at IS NULL AND cancelled_at IS NULL AND cancellation_reason IS NULL) OR (status = 'PROCESSING' AND started_at IS NOT NULL AND fulfilled_at IS NULL AND cancelled_at IS NULL AND cancellation_reason IS NULL) OR (status = 'FULFILLED' AND started_at IS NOT NULL AND fulfilled_at IS NOT NULL AND cancelled_at IS NULL AND cancellation_reason IS NULL) OR (status = 'CANCELLED' AND fulfilled_at IS NULL AND cancelled_at IS NOT NULL))");

        Schema::create('fulfillment_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->uuid('fulfillment_id');
            $table->uuid('order_item_id');
            $table->uuid('inventory_reservation_id');
            $table->uuid('inventory_location_id');
            $table->uuid('product_variant_id');
            $table->unsignedBigInteger('quantity');
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['fulfillment_id', 'order_item_id', 'inventory_reservation_id'], 'fulfillment_items_allocation_unique');
            $table->index(['tenant_id', 'order_item_id']);
            $table->index(['tenant_id', 'inventory_reservation_id']);
            $table->foreign(['fulfillment_id', 'order_id', 'store_id', 'inventory_location_id', 'tenant_id'], 'fulfillment_items_fulfillment_fk')
                ->references(['id', 'order_id', 'store_id', 'inventory_location_id', 'tenant_id'])->on('fulfillments')->restrictOnDelete();
            $table->foreign(['order_item_id', 'order_id', 'store_id', 'product_variant_id', 'tenant_id'], 'fulfillment_items_order_item_fk')
                ->references(['id', 'order_id', 'store_id', 'product_variant_id', 'tenant_id'])->on('order_items')->restrictOnDelete();
            $table->foreign(['inventory_reservation_id', 'inventory_location_id', 'product_variant_id', 'tenant_id'], 'fulfillment_items_reservation_fk')
                ->references(['id', 'inventory_location_id', 'product_variant_id', 'tenant_id'])->on('inventory_reservations')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE fulfillment_items ADD CONSTRAINT fulfillment_items_quantity_check CHECK (quantity > 0 AND quantity <= 9999)');

        Schema::create('fulfillment_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('fulfillment_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['fulfillment_id', 'created_at']);
            $table->foreign(['fulfillment_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('fulfillments')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE fulfillment_status_histories ADD CONSTRAINT fulfillment_histories_from_check CHECK (from_status IS NULL OR from_status IN ('PENDING', 'PROCESSING', 'FULFILLED', 'CANCELLED'))");
        DB::statement("ALTER TABLE fulfillment_status_histories ADD CONSTRAINT fulfillment_histories_to_check CHECK (to_status IN ('PENDING', 'PROCESSING', 'FULFILLED', 'CANCELLED'))");
        DB::statement('ALTER TABLE fulfillment_status_histories ADD CONSTRAINT fulfillment_histories_transition_check CHECK (from_status IS NULL OR from_status <> to_status)');

        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->uuid('fulfillment_id')->unique();
            $table->string('status', 20);
            $table->string('carrier_name', 120)->nullable();
            $table->string('service_name', 120)->nullable();
            $table->string('tracking_number', 160)->nullable();
            $table->string('tracking_url', 2048)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('ready_at')->nullable();
            $table->timestampTz('shipped_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'store_id', 'created_at']);
            $table->index(['order_id', 'status']);
            $table->index('tracking_number');
            $table->foreign(['fulfillment_id', 'order_id', 'store_id', 'tenant_id'])
                ->references(['id', 'order_id', 'store_id', 'tenant_id'])->on('fulfillments')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE shipments ADD CONSTRAINT shipments_status_check CHECK (status IN ('PENDING', 'READY', 'SHIPPED', 'DELIVERED', 'CANCELLED'))");
        DB::statement("ALTER TABLE shipments ADD CONSTRAINT shipments_timestamps_check CHECK ((status = 'PENDING' AND ready_at IS NULL AND shipped_at IS NULL AND delivered_at IS NULL AND cancelled_at IS NULL) OR (status = 'READY' AND ready_at IS NOT NULL AND shipped_at IS NULL AND delivered_at IS NULL AND cancelled_at IS NULL) OR (status = 'SHIPPED' AND ready_at IS NOT NULL AND shipped_at IS NOT NULL AND delivered_at IS NULL AND cancelled_at IS NULL) OR (status = 'DELIVERED' AND ready_at IS NOT NULL AND shipped_at IS NOT NULL AND delivered_at IS NOT NULL AND cancelled_at IS NULL) OR (status = 'CANCELLED' AND shipped_at IS NULL AND delivered_at IS NULL AND cancelled_at IS NOT NULL))");

        Schema::create('shipment_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('shipment_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['shipment_id', 'created_at']);
            $table->foreign(['shipment_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('shipments')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE shipment_status_histories ADD CONSTRAINT shipment_histories_from_check CHECK (from_status IS NULL OR from_status IN ('PENDING', 'READY', 'SHIPPED', 'DELIVERED', 'CANCELLED'))");
        DB::statement("ALTER TABLE shipment_status_histories ADD CONSTRAINT shipment_histories_to_check CHECK (to_status IN ('PENDING', 'READY', 'SHIPPED', 'DELIVERED', 'CANCELLED'))");
        DB::statement('ALTER TABLE shipment_status_histories ADD CONSTRAINT shipment_histories_transition_check CHECK (from_status IS NULL OR from_status <> to_status)');

        $this->createAppendOnlyTrigger('fulfillment_status_histories');
        $this->createAppendOnlyTrigger('shipment_status_histories');
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_histories');
        DB::statement('DROP FUNCTION IF EXISTS prevent_shipment_status_histories_mutation()');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('fulfillment_status_histories');
        DB::statement('DROP FUNCTION IF EXISTS prevent_fulfillment_status_histories_mutation()');
        Schema::dropIfExists('fulfillment_items');
        Schema::dropIfExists('fulfillments');
        Schema::table('inventory_reservations', function (Blueprint $table) {
            $table->dropUnique('inventory_reservations_fulfillment_reference_unique');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique('order_items_fulfillment_reference_unique');
        });
    }

    private function createAppendOnlyTrigger(string $table): void
    {
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION prevent_{$table}_mutation() RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION '{$table} is append-only';
            END;
            \$\$ LANGUAGE plpgsql;
            CREATE TRIGGER {$table}_append_only
            BEFORE UPDATE OR DELETE ON {$table}
            FOR EACH ROW EXECUTE FUNCTION prevent_{$table}_mutation();
        SQL);
    }
};
