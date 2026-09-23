<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->uuid('default_price_list_id')->nullable();
            $table->unique(['id', 'tenant_id'], 'stores_id_tenant_unique');
            $table->foreign(['default_price_list_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('price_lists')->restrictOnDelete();
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['id', 'product_id', 'tenant_id'], 'product_variants_id_product_tenant_unique');
        });

        Schema::create('store_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('product_id');
            $table->uuid('price_list_id')->nullable();
            $table->string('status', 20);
            $table->string('storefront_slug', 180);
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'product_id', 'tenant_id'], 'store_listings_id_product_tenant_unique');
            $table->unique(['store_id', 'product_id']);
            $table->unique(['store_id', 'storefront_slug']);
            $table->index(['tenant_id', 'store_id', 'status']);
            $table->foreign(['store_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('stores')->cascadeOnDelete();
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('products')->restrictOnDelete();
            $table->foreign(['price_list_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('price_lists')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE store_listings ADD CONSTRAINT store_listings_status_check CHECK (status IN ('DRAFT', 'PUBLISHED', 'UNPUBLISHED', 'ARCHIVED'))");
        DB::statement("ALTER TABLE store_listings ADD CONSTRAINT store_listings_slug_check CHECK (storefront_slug = lower(storefront_slug) AND storefront_slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$')");

        Schema::create('store_listing_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_listing_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique(['store_listing_id', 'product_variant_id']);
            $table->index(['tenant_id', 'store_listing_id', 'is_enabled']);
            $table->foreign(['store_listing_id', 'product_id', 'tenant_id'])
                ->references(['id', 'product_id', 'tenant_id'])->on('store_listings')->cascadeOnDelete();
            $table->foreign(['product_variant_id', 'product_id', 'tenant_id'])
                ->references(['id', 'product_id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_listing_variants');
        Schema::dropIfExists('store_listings');
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropUnique('product_variants_id_product_tenant_unique'));
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['default_price_list_id', 'tenant_id']);
            $table->dropUnique('stores_id_tenant_unique');
            $table->dropColumn('default_price_list_id');
        });
    }
};
