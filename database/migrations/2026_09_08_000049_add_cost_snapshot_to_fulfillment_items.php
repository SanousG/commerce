<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_items', function (Blueprint $table) {
            $table->decimal('unit_cost_snapshot', 19, 6)->nullable();
            $table->decimal('cogs_amount', 19, 4)->nullable();
            $table->char('cost_currency', 3)->nullable();
        });
        DB::statement("ALTER TABLE fulfillment_items ADD CONSTRAINT fulfillment_items_cost_snapshot_check CHECK ((unit_cost_snapshot IS NULL AND cogs_amount IS NULL AND cost_currency IS NULL) OR (unit_cost_snapshot >= 0 AND cogs_amount >= 0 AND cost_currency ~ '^[A-Z]{3}$' AND cogs_amount = ROUND(unit_cost_snapshot * quantity, 4)))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION protect_fulfillment_item_cost_snapshot() RETURNS trigger AS $$
            BEGIN
                IF OLD.unit_cost_snapshot IS NOT NULL AND
                   ROW(NEW.unit_cost_snapshot, NEW.cogs_amount, NEW.cost_currency)
                   IS DISTINCT FROM ROW(OLD.unit_cost_snapshot, OLD.cogs_amount, OLD.cost_currency) THEN
                    RAISE EXCEPTION 'Fulfillment cost snapshots are immutable';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fulfillment_item_cost_snapshot_immutable
            BEFORE UPDATE OF unit_cost_snapshot, cogs_amount, cost_currency ON fulfillment_items
            FOR EACH ROW EXECUTE FUNCTION protect_fulfillment_item_cost_snapshot();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS fulfillment_item_cost_snapshot_immutable ON fulfillment_items');
        DB::statement('DROP FUNCTION IF EXISTS protect_fulfillment_item_cost_snapshot()');
        Schema::table('fulfillment_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost_snapshot', 'cogs_amount', 'cost_currency']);
        });
    }
};
