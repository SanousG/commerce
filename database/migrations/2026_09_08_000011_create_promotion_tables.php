<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('internal_code', 60)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20);
            $table->string('discount_type', 30);
            $table->decimal('discount_value', 19, 4);
            $table->char('currency', 3)->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->integer('priority')->default(0);
            $table->string('scope', 20);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'priority']);
            $table->index('starts_at');
            $table->index('ends_at');
        });
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT promotions_status_check CHECK (status IN ('DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED'))");
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT promotions_discount_type_check CHECK (discount_type IN ('PERCENTAGE', 'FIXED_AMOUNT'))");
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT promotions_scope_check CHECK (scope IN ('ALL', 'SELECTED'))");
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT promotions_discount_value_check CHECK (discount_value > 0 AND discount_value::text NOT IN ('NaN', 'Infinity', '-Infinity') AND (discount_type <> 'PERCENTAGE' OR discount_value <= 100))");
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT promotions_currency_check CHECK ((discount_type = 'PERCENTAGE' AND currency IS NULL) OR (discount_type = 'FIXED_AMOUNT' AND currency ~ '^[A-Z]{3}$'))");
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT promotions_dates_check CHECK (starts_at IS NULL OR ends_at IS NULL OR starts_at < ends_at)');
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT promotions_priority_check CHECK (priority BETWEEN -1000000 AND 1000000)');
        DB::statement('CREATE UNIQUE INDEX promotions_tenant_internal_code_unique ON promotions (tenant_id, upper(internal_code)) WHERE internal_code IS NOT NULL');

        $this->createTargetTable('promotion_products', 'product_id', 'products');
        $this->createTargetTable('promotion_categories', 'category_id', 'categories');
        $this->createTargetTable('promotion_variants', 'product_variant_id', 'product_variants');
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_variants');
        Schema::dropIfExists('promotion_categories');
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('promotions');
    }

    private function createTargetTable(string $tableName, string $targetColumn, string $targetTable): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($targetColumn, $targetTable) {
            $table->uuid('tenant_id');
            $table->uuid('promotion_id');
            $table->uuid($targetColumn);
            $table->primary(['promotion_id', $targetColumn]);
            $table->index(['tenant_id', $targetColumn]);
            $table->foreign(['promotion_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('promotions')->cascadeOnDelete();
            $table->foreign([$targetColumn, 'tenant_id'])
                ->references(['id', 'tenant_id'])->on($targetTable)->cascadeOnDelete();
        });
    }
};
