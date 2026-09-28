<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('status', 16);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status', 'name']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE tax_categories ADD CONSTRAINT tax_categories_code_check CHECK (code IN ('STANDARD', 'EXEMPT', 'UNAFFECTED'))");
        DB::statement("ALTER TABLE tax_categories ADD CONSTRAINT tax_categories_status_check CHECK (status IN ('ACTIVE', 'INACTIVE'))");
        DB::statement("ALTER TABLE tax_categories ADD CONSTRAINT tax_categories_name_check CHECK (btrim(name) <> '')");

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('tax_category_id');
            $table->string('name', 120);
            $table->decimal('rate', 9, 6);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status', 16);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'tax_category_id', 'status', 'valid_from', 'valid_until'], 'tax_rates_effective_lookup_index');
            $table->foreign(['tax_category_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('tax_categories')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE tax_rates ADD CONSTRAINT tax_rates_rate_check CHECK (rate >= 0 AND rate <= 100)');
        DB::statement("ALTER TABLE tax_rates ADD CONSTRAINT tax_rates_status_check CHECK (status IN ('ACTIVE', 'INACTIVE'))");
        DB::statement("ALTER TABLE tax_rates ADD CONSTRAINT tax_rates_name_check CHECK (btrim(name) <> '')");
        DB::statement('ALTER TABLE tax_rates ADD CONSTRAINT tax_rates_dates_check CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_categories');
    }
};
