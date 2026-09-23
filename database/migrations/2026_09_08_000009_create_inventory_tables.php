<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 40);
            $table->string('status', 20);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("ALTER TABLE inventory_locations ADD CONSTRAINT inventory_locations_status_check CHECK (status IN ('ACTIVE', 'INACTIVE', 'ARCHIVED'))");
        DB::statement("ALTER TABLE inventory_locations ADD CONSTRAINT inventory_locations_name_check CHECK (btrim(name) <> '')");
        DB::statement("ALTER TABLE inventory_locations ADD CONSTRAINT inventory_locations_code_check CHECK (code ~ '^[A-Z0-9][A-Z0-9_-]*$')");
        DB::statement("ALTER TABLE inventory_locations ADD CONSTRAINT inventory_locations_default_active_check CHECK (NOT is_default OR status = 'ACTIVE')");
        DB::statement('CREATE UNIQUE INDEX inventory_locations_tenant_code_unique ON inventory_locations (tenant_id, upper(code))');
        DB::statement('CREATE UNIQUE INDEX inventory_locations_one_default_unique ON inventory_locations (tenant_id) WHERE is_default');

        $this->backfillDefaultLocations();
        $this->createDefaultLocationConstraint();

        Schema::create('inventory_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('inventory_location_id');
            $table->uuid('product_variant_id');
            $table->bigInteger('on_hand')->default(0);
            $table->bigInteger('reserved')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['inventory_location_id', 'product_variant_id']);
            $table->index(['tenant_id', 'product_variant_id']);
            $table->foreign(['inventory_location_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
            $table->foreign(['product_variant_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE inventory_levels ADD CONSTRAINT inventory_levels_nonnegative_check CHECK (on_hand >= 0 AND reserved >= 0 AND reserved <= on_hand)');

        Schema::create('inventory_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('source_location_id');
            $table->uuid('destination_location_id');
            $table->string('status', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampTz('completed_at');
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'completed_at']);
            $table->foreign(['source_location_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
            $table->foreign(['destination_location_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE inventory_transfers ADD CONSTRAINT inventory_transfers_status_check CHECK (status = 'COMPLETED')");
        DB::statement('ALTER TABLE inventory_transfers ADD CONSTRAINT inventory_transfers_distinct_locations_check CHECK (source_location_id <> destination_location_id)');

        Schema::create('inventory_transfer_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('inventory_transfer_id');
            $table->uuid('product_variant_id');
            $table->bigInteger('quantity');
            $table->timestamps();

            $table->unique(['inventory_transfer_id', 'product_variant_id']);
            $table->index(['tenant_id', 'product_variant_id']);
            $table->foreign(['inventory_transfer_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('inventory_transfers')->cascadeOnDelete();
            $table->foreign(['product_variant_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE inventory_transfer_lines ADD CONSTRAINT inventory_transfer_lines_quantity_check CHECK (quantity > 0)');

        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('inventory_location_id');
            $table->uuid('product_variant_id');
            $table->string('reference_type', 80)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->bigInteger('quantity');
            $table->string('status', 20);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('committed_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->index(['tenant_id', 'reference_type', 'reference_id']);
            $table->foreign(['inventory_location_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
            $table->foreign(['product_variant_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_status_check CHECK (status IN ('ACTIVE', 'RELEASED', 'COMMITTED', 'EXPIRED'))");
        DB::statement('ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE inventory_reservations ADD CONSTRAINT inventory_reservations_reference_check CHECK ((reference_type IS NULL) = (reference_id IS NULL))');

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('inventory_location_id');
            $table->uuid('product_variant_id');
            $table->string('type', 30);
            $table->bigInteger('quantity_delta');
            $table->bigInteger('quantity_before');
            $table->bigInteger('quantity_after');
            $table->string('reference_type', 80)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->uuid('correlation_id');
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'product_variant_id', 'occurred_at']);
            $table->index(['tenant_id', 'inventory_location_id', 'occurred_at']);
            $table->index(['tenant_id', 'reference_type', 'reference_id']);
            $table->index(['tenant_id', 'correlation_id']);
            $table->foreign(['inventory_location_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
            $table->foreign(['product_variant_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type IN ('OPENING', 'ADJUSTMENT_IN', 'ADJUSTMENT_OUT', 'TRANSFER_IN', 'TRANSFER_OUT', 'RESERVATION_COMMIT'))");
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_quantity_check CHECK (quantity_delta <> 0 AND quantity_before >= 0 AND quantity_after >= 0 AND quantity_after = quantity_before + quantity_delta)');
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_reference_check CHECK ((reference_type IS NULL) = (reference_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_transfer_lines');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('inventory_levels');
        DB::statement('DROP TRIGGER tenants_default_inventory_location_check ON tenants');
        DB::statement('DROP TRIGGER inventory_locations_default_check ON inventory_locations');
        DB::statement('DROP FUNCTION enforce_tenant_default_inventory_location()');
        Schema::dropIfExists('inventory_locations');
    }

    private function backfillDefaultLocations(): void
    {
        $name = trim((string) config('commerce.default_inventory_location_name', 'Almacén principal'));
        $code = str((string) config('commerce.default_inventory_location_code', 'MAIN'))->trim()->upper()->toString();
        if ($name === '' || strlen($name) > 120 || strlen($code) > 40
            || preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $code) !== 1) {
            throw new RuntimeException('The configured default inventory location is invalid.');
        }

        DB::statement(<<<'SQL'
            INSERT INTO inventory_locations (id, tenant_id, name, code, status, is_default, created_at, updated_at)
            SELECT (
                '01995000-0000-7' || substr(md5(tenants.id::text || ':default-inventory-location'), 1, 3) ||
                '-8' || substr(md5(tenants.id::text || ':default-inventory-location'), 4, 3) ||
                '-' || substr(md5(tenants.id::text || ':default-inventory-location'), 7, 12)
            )::uuid,
            tenants.id, ?, ?, 'ACTIVE', true, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM tenants
            WHERE NOT EXISTS (
                SELECT 1 FROM inventory_locations WHERE inventory_locations.tenant_id = tenants.id AND inventory_locations.is_default
            )
        SQL, [$name, $code]);
    }

    private function createDefaultLocationConstraint(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS enforce_tenant_default_inventory_location() CASCADE');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION enforce_tenant_default_inventory_location() RETURNS trigger AS $$
            DECLARE
                checked_tenant_id uuid;
            BEGIN
                IF TG_TABLE_NAME = 'tenants' THEN
                    checked_tenant_id := NEW.id;
                ELSIF TG_OP = 'DELETE' THEN
                    checked_tenant_id := OLD.tenant_id;
                ELSE
                    checked_tenant_id := NEW.tenant_id;
                END IF;

                IF EXISTS (SELECT 1 FROM tenants WHERE id = checked_tenant_id)
                   AND (SELECT COUNT(*) FROM inventory_locations WHERE tenant_id = checked_tenant_id AND is_default AND status = 'ACTIVE') <> 1 THEN
                    RAISE EXCEPTION 'A tenant requires exactly one active default inventory location' USING ERRCODE = '23514';
                END IF;

                IF TG_TABLE_NAME = 'inventory_locations' AND TG_OP = 'UPDATE' THEN
                    IF OLD.tenant_id IS DISTINCT FROM NEW.tenant_id
                       AND EXISTS (SELECT 1 FROM tenants WHERE id = OLD.tenant_id)
                       AND (SELECT COUNT(*) FROM inventory_locations WHERE tenant_id = OLD.tenant_id AND is_default AND status = 'ACTIVE') <> 1 THEN
                        RAISE EXCEPTION 'A tenant requires exactly one active default inventory location' USING ERRCODE = '23514';
                    END IF;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER inventory_locations_default_check
            AFTER INSERT OR UPDATE OR DELETE ON inventory_locations
            DEFERRABLE INITIALLY IMMEDIATE
            FOR EACH ROW EXECUTE FUNCTION enforce_tenant_default_inventory_location()
        SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER tenants_default_inventory_location_check
            AFTER INSERT ON tenants
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION enforce_tenant_default_inventory_location()
        SQL);
    }
};
