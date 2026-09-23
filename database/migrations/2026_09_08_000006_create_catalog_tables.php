<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_status_check CHECK (status IN ('DRAFT', 'ACTIVE', 'ARCHIVED'))");

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('status', 20);
            $table->uuid('parent_id')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'position']);
            $table->foreign(['parent_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('categories')
                ->restrictOnDelete();
        });
        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_status_check CHECK (status IN ('ACTIVE', 'INACTIVE', 'ARCHIVED'))");
        DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_not_self_parent_check CHECK (parent_id IS NULL OR parent_id <> id)');

        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('product_id');
            $table->string('sku', 120);
            $table->string('barcode', 120)->nullable();
            $table->string('name', 160)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['id', 'product_id']);
            $table->unique(['tenant_id', 'barcode']);
            $table->index(['product_id', 'status']);
            $table->foreign(['product_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('products')
                ->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE product_variants ADD CONSTRAINT product_variants_status_check CHECK (status IN ('ACTIVE', 'INACTIVE', 'ARCHIVED'))");
        DB::statement("ALTER TABLE product_variants ADD CONSTRAINT product_variants_default_active_check CHECK (NOT is_default OR status = 'ACTIVE')");
        DB::statement('CREATE UNIQUE INDEX product_variants_tenant_sku_unique ON product_variants (tenant_id, lower(sku))');
        DB::statement('CREATE UNIQUE INDEX product_variants_one_default_unique ON product_variants (product_id) WHERE is_default');

        Schema::create('product_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('name', 80);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['id', 'product_id']);
            $table->unique(['product_id', 'name']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::create('product_option_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('product_option_id');
            $table->string('value', 100);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['id', 'product_option_id', 'product_id']);
            $table->unique(['product_option_id', 'value']);
            $table->foreign(['product_option_id', 'product_id'])
                ->references(['id', 'product_id'])
                ->on('product_options')
                ->cascadeOnDelete();
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('product_id');
            $table->uuid('category_id');
            $table->primary(['product_id', 'category_id']);
            $table->index(['tenant_id', 'category_id']);
            $table->foreign(['product_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->foreign(['category_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('categories')
                ->cascadeOnDelete();
        });

        Schema::create('product_variant_option_values', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('product_variant_id');
            $table->uuid('product_option_id');
            $table->uuid('product_option_value_id');
            $table->primary(['product_variant_id', 'product_option_value_id']);
            $table->unique(['product_variant_id', 'product_option_id']);
            $table->foreign(['product_variant_id', 'product_id'])
                ->references(['id', 'product_id'])
                ->on('product_variants')
                ->cascadeOnDelete();
            $table->foreign(['product_option_value_id', 'product_option_id', 'product_id'])
                ->references(['id', 'product_option_id', 'product_id'])
                ->on('product_option_values')
                ->cascadeOnDelete();
        });

        $this->createDefaultVariantConstraint();
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER products_default_variant_check ON products');
        DB::statement('DROP TRIGGER variants_default_variant_check ON product_variants');
        DB::statement('DROP FUNCTION enforce_product_default_variant()');

        Schema::dropIfExists('product_variant_option_values');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('products');
    }

    private function createDefaultVariantConstraint(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS enforce_product_default_variant() CASCADE');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION enforce_product_default_variant() RETURNS trigger AS $$
            DECLARE
                checked_product_id uuid;
            BEGIN
                IF TG_TABLE_NAME = 'products' THEN
                    checked_product_id := NEW.id;
                ELSIF TG_OP = 'DELETE' THEN
                    checked_product_id := OLD.product_id;
                ELSE
                    checked_product_id := NEW.product_id;
                END IF;

                IF EXISTS (SELECT 1 FROM products WHERE id = checked_product_id)
                   AND (SELECT COUNT(*) FROM product_variants WHERE product_id = checked_product_id AND is_default AND status = 'ACTIVE') <> 1 THEN
                    RAISE EXCEPTION 'A product requires exactly one active default variant' USING ERRCODE = '23514';
                END IF;

                IF TG_TABLE_NAME = 'product_variants' AND TG_OP = 'UPDATE' THEN
                    IF OLD.product_id IS DISTINCT FROM NEW.product_id
                       AND EXISTS (SELECT 1 FROM products WHERE id = OLD.product_id)
                       AND (SELECT COUNT(*) FROM product_variants WHERE product_id = OLD.product_id AND is_default AND status = 'ACTIVE') <> 1 THEN
                        RAISE EXCEPTION 'A product requires exactly one active default variant' USING ERRCODE = '23514';
                    END IF;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER products_default_variant_check
            AFTER INSERT OR UPDATE ON products
            DEFERRABLE INITIALLY IMMEDIATE
            FOR EACH ROW EXECUTE FUNCTION enforce_product_default_variant()
        SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER variants_default_variant_check
            AFTER INSERT OR UPDATE OR DELETE ON product_variants
            DEFERRABLE INITIALLY IMMEDIATE
            FOR EACH ROW EXECUTE FUNCTION enforce_product_default_variant()
        SQL);
    }
};
