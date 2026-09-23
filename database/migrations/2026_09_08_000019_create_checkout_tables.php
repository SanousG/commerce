<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('cart_id');
            $table->uuid('customer_id')->nullable();
            $table->string('token', 64)->unique();
            $table->string('idempotency_key', 100);
            $table->string('status', 20);
            $table->string('delivery_type', 20);
            $table->char('currency', 3)->nullable();
            $table->string('customer_name', 160)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 32)->nullable();
            foreach (['shipping', 'billing'] as $prefix) {
                $table->string("{$prefix}_recipient_name", 160)->nullable();
                $table->string("{$prefix}_phone", 32)->nullable();
                $table->char("{$prefix}_country_code", 2)->nullable();
                $table->string("{$prefix}_region", 120)->nullable();
                $table->string("{$prefix}_city", 120)->nullable();
                $table->string("{$prefix}_district", 120)->nullable();
                $table->string("{$prefix}_address_line_1", 255)->nullable();
                $table->string("{$prefix}_address_line_2", 255)->nullable();
                $table->string("{$prefix}_postal_code", 32)->nullable();
                $table->string("{$prefix}_reference", 255)->nullable();
            }
            $table->decimal('subtotal_before_discounts', 19, 4)->default(0);
            $table->decimal('discount_total', 19, 4)->default(0);
            $table->decimal('subtotal', 19, 4)->default(0);
            $table->decimal('shipping_total', 19, 4)->default(0);
            $table->decimal('tax_total', 19, 4)->default(0);
            $table->decimal('grand_total', 19, 4)->default(0);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id'], 'checkout_sessions_id_tenant_unique');
            $table->unique(['id', 'store_id', 'tenant_id'], 'checkout_sessions_id_store_tenant_unique');
            $table->unique(['cart_id', 'idempotency_key'], 'checkout_sessions_cart_idempotency_unique');
            $table->index('tenant_id');
            $table->index(['store_id', 'status']);
            $table->index('cart_id');
            $table->index('customer_id');
            $table->index('expires_at');
            $table->index('idempotency_key');
            $table->foreign(['cart_id', 'store_id', 'tenant_id'])->references(['id', 'store_id', 'tenant_id'])->on('carts')->restrictOnDelete();
            $table->foreign(['customer_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('customers')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_status_check CHECK (status IN ('PENDING', 'READY', 'COMPLETED', 'EXPIRED', 'CANCELLED', 'FAILED'))");
        DB::statement("ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_delivery_type_check CHECK (delivery_type IN ('SHIPPING', 'PICKUP'))");
        DB::statement("ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_currency_check CHECK (currency IS NULL OR currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_token_check CHECK (token ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_idempotency_check CHECK (btrim(idempotency_key) <> '')");
        DB::statement("ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_contact_check CHECK (email IS NULL OR btrim(email) <> '')");
        DB::statement("ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_shipping_country_check CHECK (shipping_country_code IS NULL OR shipping_country_code ~ '^[A-Z]{2}$')");
        DB::statement("ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_billing_country_check CHECK (billing_country_code IS NULL OR billing_country_code ~ '^[A-Z]{2}$')");
        DB::statement('ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_money_check CHECK (subtotal_before_discounts >= 0 AND discount_total >= 0 AND subtotal >= 0 AND shipping_total >= 0 AND tax_total >= 0 AND grand_total >= 0)');
        DB::statement('ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_totals_check CHECK (subtotal_before_discounts - discount_total = subtotal AND subtotal + shipping_total + tax_total = grand_total)');
        DB::statement("CREATE UNIQUE INDEX checkout_sessions_one_operational_per_cart_unique ON checkout_sessions (cart_id) WHERE status IN ('PENDING', 'READY')");

        Schema::create('checkout_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('checkout_session_id');
            $table->uuid('store_id');
            $table->uuid('store_listing_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id');
            $table->uuid('price_list_id');
            $table->uuid('promotion_id')->nullable();
            $table->string('product_name', 160);
            $table->string('variant_name', 160)->nullable();
            $table->string('sku', 100);
            $table->unsignedBigInteger('quantity');
            $table->decimal('base_unit_price', 19, 4);
            $table->decimal('discount_unit_amount', 19, 4);
            $table->decimal('final_unit_price', 19, 4);
            $table->decimal('line_base_total', 19, 4);
            $table->decimal('line_discount_total', 19, 4);
            $table->decimal('line_total', 19, 4);
            $table->string('promotion_name', 160)->nullable();
            $table->char('currency', 3);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['checkout_session_id', 'product_variant_id']);
            $table->index(['tenant_id', 'checkout_session_id']);
            $table->index('store_listing_id');
            $table->index('product_variant_id');
            $table->foreign(['checkout_session_id', 'store_id', 'tenant_id'])->references(['id', 'store_id', 'tenant_id'])->on('checkout_sessions')->cascadeOnDelete();
            $table->foreign(['store_listing_id', 'store_id', 'product_id', 'tenant_id'])->references(['id', 'store_id', 'product_id', 'tenant_id'])->on('store_listings')->restrictOnDelete();
            $table->foreign(['product_variant_id', 'product_id', 'tenant_id'])->references(['id', 'product_id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
            $table->foreign(['price_list_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('price_lists')->restrictOnDelete();
            $table->foreign(['promotion_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('promotions')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE checkout_lines ADD CONSTRAINT checkout_lines_quantity_check CHECK (quantity > 0 AND quantity <= 9999)');
        DB::statement("ALTER TABLE checkout_lines ADD CONSTRAINT checkout_lines_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE checkout_lines ADD CONSTRAINT checkout_lines_money_check CHECK (base_unit_price >= 0 AND discount_unit_amount >= 0 AND final_unit_price >= 0 AND line_base_total >= 0 AND line_discount_total >= 0 AND line_total >= 0)');
        DB::statement('ALTER TABLE checkout_lines ADD CONSTRAINT checkout_lines_arithmetic_check CHECK (base_unit_price - discount_unit_amount = final_unit_price AND base_unit_price * quantity = line_base_total AND discount_unit_amount * quantity = line_discount_total AND final_unit_price * quantity = line_total)');
        DB::statement('ALTER TABLE checkout_lines ADD CONSTRAINT checkout_lines_promotion_snapshot_check CHECK ((promotion_id IS NULL) = (promotion_name IS NULL))');

        DB::statement("CREATE UNIQUE INDEX inventory_reservations_checkout_line_location_unique ON inventory_reservations (tenant_id, reference_id, inventory_location_id) WHERE reference_type = 'CHECKOUT_LINE'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_reservations_checkout_line_location_unique');
        Schema::dropIfExists('checkout_lines');
        Schema::dropIfExists('checkout_sessions');
    }
};
