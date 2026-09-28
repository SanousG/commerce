<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS procurement_product_variants_id_tenant_unique ON product_variants (id, tenant_id)');

        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('status', 16);
            $table->string('contact_name', 160)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status', 'name']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_status_check CHECK (status IN ('ACTIVE','INACTIVE','ARCHIVED'))");
        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_code_check CHECK (code = upper(btrim(code)) AND code ~ '^[A-Z0-9][A-Z0-9_-]*$')");
        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_name_check CHECK (btrim(name) <> '')");

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('supplier_id');
            $table->uuid('product_variant_id');
            $table->string('supplier_sku', 100)->nullable();
            $table->string('status', 16);
            $table->decimal('unit_cost', 19, 4);
            $table->char('currency', 3);
            $table->unsignedBigInteger('minimum_order_quantity')->default(1);
            $table->unsignedBigInteger('order_multiple')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'product_variant_id', 'tenant_id'], 'supplier_products_id_variant_tenant_unique');
            $table->unique(['id', 'supplier_id', 'product_variant_id', 'tenant_id'], 'supplier_products_variant_tenant_unique');
            $table->unique(['supplier_id', 'product_variant_id']);
            $table->index(['tenant_id', 'product_variant_id', 'status']);
            $table->foreign(['supplier_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('suppliers')->restrictOnDelete();
            $table->foreign(['product_variant_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('product_variants')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE supplier_products ADD CONSTRAINT supplier_products_status_check CHECK (status IN ('ACTIVE','INACTIVE','ARCHIVED'))");
        DB::statement("ALTER TABLE supplier_products ADD CONSTRAINT supplier_products_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE supplier_products ADD CONSTRAINT supplier_products_values_check CHECK (unit_cost >= 0 AND minimum_order_quantity > 0 AND (order_multiple IS NULL OR order_multiple > 0) AND (lead_time_days IS NULL OR lead_time_days <= 3650))');
        DB::statement("ALTER TABLE supplier_products ADD CONSTRAINT supplier_products_sku_check CHECK (supplier_sku IS NULL OR btrim(supplier_sku) <> '')");
        DB::statement("ALTER TABLE supplier_products ADD CONSTRAINT supplier_products_preferred_active_check CHECK (NOT is_preferred OR status = 'ACTIVE')");
        DB::statement('CREATE UNIQUE INDEX supplier_products_supplier_sku_unique ON supplier_products (supplier_id, lower(supplier_sku)) WHERE supplier_sku IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX supplier_products_one_preferred_variant_unique ON supplier_products (tenant_id, product_variant_id) WHERE is_preferred AND status = \'ACTIVE\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('suppliers');
        DB::statement('DROP INDEX IF EXISTS procurement_product_variants_id_tenant_unique');
    }
};
