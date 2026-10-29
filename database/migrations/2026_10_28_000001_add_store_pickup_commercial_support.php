<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->boolean('pickup_enabled')->default(false);
            $table->uuid('pickup_inventory_location_id')->nullable();
            $table->string('pickup_instructions', 1000)->nullable();
            $table->foreign(['pickup_inventory_location_id', 'tenant_id'], 'store_settings_pickup_location_fk')
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
        });
        DB::statement(<<<'SQL'
            ALTER TABLE store_settings ADD CONSTRAINT store_settings_pickup_check CHECK (
                NOT pickup_enabled OR (
                    pickup_inventory_location_id IS NOT NULL
                    AND address_line_1 IS NOT NULL AND btrim(address_line_1) <> ''
                    AND district IS NOT NULL AND btrim(district) <> ''
                    AND province IS NOT NULL AND btrim(province) <> ''
                    AND department IS NOT NULL AND btrim(department) <> ''
                    AND country_code IS NOT NULL AND btrim(country_code) <> ''
                )
            )
        SQL);

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $this->pickupSnapshot($table);
            $table->string('expected_payment_method', 24)->nullable();
            $table->string('payment_terms', 16)->nullable();
            $table->string('cod_deposit_type', 20)->nullable();
            $table->decimal('cod_deposit_value', 19, 4)->nullable();
            $table->foreign(['pickup_inventory_location_id', 'tenant_id'], 'checkout_sessions_pickup_location_fk')
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
        });
        $this->addSnapshotConstraint('checkout_sessions');

        Schema::table('orders', function (Blueprint $table) {
            $this->pickupSnapshot($table);
            $table->foreign(['pickup_inventory_location_id', 'tenant_id'], 'orders_pickup_location_fk')
                ->references(['id', 'tenant_id'])->on('inventory_locations')->restrictOnDelete();
        });
        $this->addSnapshotConstraint('orders');

        Schema::table('order_payment_plans', function (Blueprint $table) {
            $table->string('expected_method', 24)->nullable();
        });
        DB::statement("ALTER TABLE order_payment_plans ADD CONSTRAINT order_payment_plans_expected_method_check CHECK (expected_method IS NULL OR expected_method IN ('ONLINE', 'BANK_TRANSFER', 'CASH'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE order_payment_plans DROP CONSTRAINT IF EXISTS order_payment_plans_expected_method_check');
        Schema::table('order_payment_plans', fn (Blueprint $table) => $table->dropColumn('expected_method'));

        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_pickup_snapshot_check');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('orders_pickup_location_fk');
            $this->dropPickupSnapshot($table);
        });

        DB::statement('ALTER TABLE checkout_sessions DROP CONSTRAINT IF EXISTS checkout_sessions_pickup_snapshot_check');
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropForeign('checkout_sessions_pickup_location_fk');
            $this->dropPickupSnapshot($table);
            $table->dropColumn(['expected_payment_method', 'payment_terms', 'cod_deposit_type', 'cod_deposit_value']);
        });

        DB::statement('ALTER TABLE store_settings DROP CONSTRAINT IF EXISTS store_settings_pickup_check');
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropForeign('store_settings_pickup_location_fk');
            $table->dropColumn(['pickup_enabled', 'pickup_inventory_location_id', 'pickup_instructions']);
        });
    }

    private function pickupSnapshot(Blueprint $table): void
    {
        $table->uuid('pickup_inventory_location_id')->nullable();
        $table->string('pickup_name', 120)->nullable();
        $table->string('pickup_address_line_1', 255)->nullable();
        $table->string('pickup_address_line_2', 255)->nullable();
        $table->string('pickup_district', 120)->nullable();
        $table->string('pickup_province', 120)->nullable();
        $table->string('pickup_department', 120)->nullable();
        $table->char('pickup_country_code', 2)->nullable();
        $table->string('pickup_postal_code', 32)->nullable();
        $table->string('pickup_instructions', 1000)->nullable();
    }

    private function dropPickupSnapshot(Blueprint $table): void
    {
        $table->dropColumn([
            'pickup_inventory_location_id', 'pickup_name', 'pickup_address_line_1', 'pickup_address_line_2',
            'pickup_district', 'pickup_province', 'pickup_department', 'pickup_country_code',
            'pickup_postal_code', 'pickup_instructions',
        ]);
    }

    private function addSnapshotConstraint(string $table): void
    {
        $payment = $table === 'checkout_sessions'
            ? <<<'SQL'
                AND expected_payment_method = 'CASH'
                AND payment_terms = 'COD'
                AND cod_deposit_type = 'NONE'
                AND cod_deposit_value = 0
            SQL
            : '';
        $paymentNulls = $table === 'checkout_sessions'
            ? ' AND expected_payment_method IS NULL AND payment_terms IS NULL AND cod_deposit_type IS NULL AND cod_deposit_value IS NULL'
            : '';

        DB::statement(<<<SQL
            ALTER TABLE {$table} ADD CONSTRAINT {$table}_pickup_snapshot_check CHECK (
                (
                    pickup_inventory_location_id IS NULL AND pickup_name IS NULL
                    AND pickup_address_line_1 IS NULL AND pickup_address_line_2 IS NULL
                    AND pickup_district IS NULL AND pickup_province IS NULL AND pickup_department IS NULL
                    AND pickup_country_code IS NULL AND pickup_postal_code IS NULL AND pickup_instructions IS NULL
                    {$paymentNulls}
                ) OR (
                    delivery_type = 'PICKUP' AND shipping_total = 0
                    AND pickup_inventory_location_id IS NOT NULL
                    AND pickup_name IS NOT NULL AND btrim(pickup_name) <> ''
                    AND pickup_address_line_1 IS NOT NULL AND btrim(pickup_address_line_1) <> ''
                    AND pickup_district IS NOT NULL AND btrim(pickup_district) <> ''
                    AND pickup_province IS NOT NULL AND btrim(pickup_province) <> ''
                    AND pickup_department IS NOT NULL AND btrim(pickup_department) <> ''
                    AND pickup_country_code ~ '^[A-Z]{2}$'
                    {$payment}
                )
            )
        SQL);
    }
};
