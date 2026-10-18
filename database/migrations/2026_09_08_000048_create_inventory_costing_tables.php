<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_valuations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('inventory_location_id');
            $table->uuid('product_variant_id');
            $table->decimal('average_unit_cost', 19, 6)->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'inventory_location_id', 'product_variant_id'], 'inventory_valuations_scope_unique');
            $table->index(['tenant_id', 'product_variant_id']);
            $table->foreign(['inventory_location_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
            $table->foreign(['product_variant_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE inventory_valuations ADD CONSTRAINT inventory_valuations_cost_check CHECK (average_unit_cost IS NULL OR average_unit_cost >= 0)');
        DB::statement("ALTER TABLE inventory_valuations ADD CONSTRAINT inventory_valuations_currency_check CHECK ((average_unit_cost IS NULL AND currency IS NULL) OR (average_unit_cost IS NOT NULL AND currency ~ '^[A-Z]{3}$'))");

        Schema::create('inventory_cost_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('inventory_location_id');
            $table->uuid('product_variant_id');
            $table->uuid('inventory_movement_id')->nullable();
            $table->string('movement_type', 30);
            $table->bigInteger('quantity');
            $table->decimal('unit_cost', 19, 6);
            $table->decimal('value_delta', 19, 4);
            $table->decimal('average_cost_before', 19, 6)->nullable();
            $table->decimal('average_cost_after', 19, 6);
            $table->char('currency', 3);
            $table->string('reference_type', 80);
            $table->uuid('reference_id');
            $table->uuid('correlation_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique('inventory_movement_id');
            $table->index(['tenant_id', 'product_variant_id', 'occurred_at'], 'inventory_cost_movements_variant_time_index');
            $table->index(['tenant_id', 'inventory_location_id', 'occurred_at'], 'inventory_cost_movements_location_time_index');
            $table->index(['tenant_id', 'reference_type', 'reference_id'], 'inventory_cost_movements_reference_index');
            $table->foreign(['inventory_location_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
            $table->foreign(['product_variant_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE inventory_cost_movements ADD CONSTRAINT inventory_cost_movements_type_check CHECK (movement_type IN ('PURCHASE_RECEIPT', 'FULFILLMENT_COGS', 'RETURN_RESTOCK', 'OPENING_COST'))");
        DB::statement("ALTER TABLE inventory_cost_movements ADD CONSTRAINT inventory_cost_movements_values_check CHECK (quantity <> 0 AND unit_cost >= 0 AND average_cost_after >= 0 AND currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE inventory_cost_movements ADD CONSTRAINT inventory_cost_movements_delta_check CHECK ((movement_type = 'FULFILLMENT_COGS' AND quantity < 0 AND value_delta <= 0) OR (movement_type <> 'FULFILLMENT_COGS' AND quantity > 0 AND value_delta >= 0))");
        DB::statement("CREATE UNIQUE INDEX inventory_cost_movements_single_source_unique ON inventory_cost_movements (tenant_id, movement_type, reference_type, reference_id) WHERE movement_type IN ('PURCHASE_RECEIPT', 'FULFILLMENT_COGS', 'OPENING_COST')");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_inventory_cost_movements_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'inventory_cost_movements is append-only';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER inventory_cost_movements_append_only
            BEFORE UPDATE OR DELETE ON inventory_cost_movements
            FOR EACH ROW EXECUTE FUNCTION prevent_inventory_cost_movements_mutation();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_movements');
        DB::statement('DROP FUNCTION IF EXISTS prevent_inventory_cost_movements_mutation()');
        Schema::dropIfExists('inventory_valuations');
    }
};
