<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_listings', function (Blueprint $table) {
            $table->unique(['id', 'store_id', 'product_id', 'tenant_id'], 'store_listings_cart_integrity_unique');
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('customer_id')->nullable();
            $table->string('token', 64)->unique();
            $table->string('status', 20);
            $table->char('currency', 3)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'store_id', 'tenant_id'], 'carts_id_store_tenant_unique');
            $table->index('tenant_id');
            $table->index(['store_id', 'status']);
            $table->index('customer_id');
            $table->index('expires_at');
            $table->foreign(['store_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('stores')->cascadeOnDelete();
            $table->foreign(['customer_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('customers')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE carts ADD CONSTRAINT carts_status_check CHECK (status IN ('ACTIVE', 'CONVERTED', 'EXPIRED', 'ABANDONED'))");
        DB::statement("ALTER TABLE carts ADD CONSTRAINT carts_currency_check CHECK (currency IS NULL OR currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE carts ADD CONSTRAINT carts_token_check CHECK (token ~ '^[a-f0-9]{64}$')");

        Schema::create('cart_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('cart_id');
            $table->uuid('store_listing_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id');
            $table->unsignedBigInteger('quantity');
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
            $table->index(['tenant_id', 'cart_id']);
            $table->index('store_listing_id');
            $table->index('product_variant_id');
            $table->foreign(['cart_id', 'store_id', 'tenant_id'])
                ->references(['id', 'store_id', 'tenant_id'])->on('carts')->cascadeOnDelete();
            $table->foreign(['store_listing_id', 'store_id', 'product_id', 'tenant_id'])
                ->references(['id', 'store_id', 'product_id', 'tenant_id'])->on('store_listings')->restrictOnDelete();
            $table->foreign(['product_variant_id', 'product_id', 'tenant_id'])
                ->references(['id', 'product_id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_check CHECK (quantity > 0 AND quantity <= 9999)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::table('store_listings', fn (Blueprint $table) => $table->dropUnique('store_listings_cart_integrity_unique'));
    }
};
