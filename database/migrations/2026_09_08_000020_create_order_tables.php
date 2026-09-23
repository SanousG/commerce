<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->uuid('tenant_id')->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('checkout_session_id')->unique();
            $table->uuid('cart_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->string('order_number', 32);
            $table->string('status', 20);
            $table->string('payment_status', 24);
            $table->string('fulfillment_status', 24);
            $table->char('currency', 3);
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
            $table->string('delivery_type', 20);
            $table->decimal('subtotal_before_discounts', 19, 4);
            $table->decimal('discount_total', 19, 4);
            $table->decimal('subtotal', 19, 4);
            $table->decimal('shipping_total', 19, 4);
            $table->decimal('tax_total', 19, 4);
            $table->decimal('grand_total', 19, 4);
            $table->timestampTz('placed_at');
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'store_id', 'tenant_id'], 'orders_id_store_tenant_unique');
            $table->unique(['tenant_id', 'order_number']);
            $table->index('tenant_id');
            $table->index(['store_id', 'placed_at']);
            $table->index('cart_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('fulfillment_status');
            $table->foreign(['checkout_session_id', 'store_id', 'tenant_id'])
                ->references(['id', 'store_id', 'tenant_id'])->on('checkout_sessions')->restrictOnDelete();
            $table->foreign(['cart_id', 'store_id', 'tenant_id'])
                ->references(['id', 'store_id', 'tenant_id'])->on('carts')->restrictOnDelete();
            $table->foreign(['customer_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('customers')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_status_check CHECK (payment_status IN ('UNPAID', 'PARTIALLY_PAID', 'PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_fulfillment_status_check CHECK (fulfillment_status IN ('UNFULFILLED', 'PARTIALLY_FULFILLED', 'FULFILLED'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_type_check CHECK (delivery_type IN ('SHIPPING', 'PICKUP'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_number_check CHECK (order_number ~ '^ORD-[0-9]{6,}$')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_shipping_country_check CHECK (shipping_country_code IS NULL OR shipping_country_code ~ '^[A-Z]{2}$')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_billing_country_check CHECK (billing_country_code IS NULL OR billing_country_code ~ '^[A-Z]{2}$')");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_money_check CHECK (subtotal_before_discounts >= 0 AND discount_total >= 0 AND subtotal >= 0 AND shipping_total >= 0 AND tax_total >= 0 AND grand_total >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_totals_check CHECK (subtotal_before_discounts - discount_total = subtotal AND subtotal + shipping_total + tax_total = grand_total)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_cancelled_at_check CHECK ((status = 'CANCELLED') = (cancelled_at IS NOT NULL))");

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->uuid('checkout_line_id')->unique();
            $table->uuid('store_listing_id')->nullable();
            $table->uuid('product_id')->nullable();
            $table->uuid('product_variant_id')->nullable();
            $table->string('product_name', 160);
            $table->string('variant_name', 160)->nullable();
            $table->string('sku', 100);
            $table->unsignedBigInteger('quantity');
            $table->uuid('price_list_id')->nullable();
            $table->decimal('base_unit_price', 19, 4);
            $table->decimal('discount_unit_amount', 19, 4);
            $table->decimal('final_unit_price', 19, 4);
            $table->decimal('line_base_total', 19, 4);
            $table->decimal('line_discount_total', 19, 4);
            $table->decimal('line_total', 19, 4);
            $table->uuid('promotion_id')->nullable();
            $table->string('promotion_name', 160)->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'order_id']);
            $table->index('product_variant_id');
            $table->foreign(['order_id', 'store_id', 'tenant_id'])
                ->references(['id', 'store_id', 'tenant_id'])->on('orders')->cascadeOnDelete();
            $table->foreign(['checkout_line_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('checkout_lines')->restrictOnDelete();
            $table->foreign(['store_listing_id', 'store_id', 'product_id', 'tenant_id'])
                ->references(['id', 'store_id', 'product_id', 'tenant_id'])->on('store_listings')->restrictOnDelete();
            $table->foreign(['product_variant_id', 'product_id', 'tenant_id'])
                ->references(['id', 'product_id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
            $table->foreign(['price_list_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('price_lists')->restrictOnDelete();
            $table->foreign(['promotion_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('promotions')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_check CHECK (quantity > 0 AND quantity <= 9999)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_money_check CHECK (base_unit_price >= 0 AND discount_unit_amount >= 0 AND final_unit_price >= 0 AND line_base_total >= 0 AND line_discount_total >= 0 AND line_total >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_arithmetic_check CHECK (base_unit_price - discount_unit_amount = final_unit_price AND base_unit_price * quantity = line_base_total AND discount_unit_amount * quantity = line_discount_total AND final_unit_price * quantity = line_total)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_catalog_refs_check CHECK ((store_listing_id IS NULL AND product_id IS NULL AND product_variant_id IS NULL) OR (store_listing_id IS NOT NULL AND product_id IS NOT NULL AND product_variant_id IS NOT NULL))');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_promotion_snapshot_check CHECK ((promotion_id IS NULL) = (promotion_name IS NULL))');

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('order_id');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
            $table->foreign(['order_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('orders')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE order_status_histories ADD CONSTRAINT order_status_histories_from_check CHECK (from_status IS NULL OR from_status IN ('PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED'))");
        DB::statement("ALTER TABLE order_status_histories ADD CONSTRAINT order_status_histories_to_check CHECK (to_status IN ('PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED'))");
        DB::statement('ALTER TABLE order_status_histories ADD CONSTRAINT order_status_histories_transition_check CHECK (from_status IS NULL OR from_status <> to_status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_number_sequences');
    }
};
