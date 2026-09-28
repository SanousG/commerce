<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_issuer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->char('country_code', 2);
            $table->string('tax_id', 80);
            $table->string('legal_name', 200);
            $table->string('trade_name', 200)->nullable();
            $table->string('fiscal_address_line_1', 255);
            $table->string('fiscal_address_line_2', 255)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('ubigeo', 20)->nullable();
            $table->string('status', 16);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE fiscal_issuer_profiles ADD CONSTRAINT fiscal_issuer_profiles_values_check CHECK (btrim(legal_name) <> '' AND btrim(tax_id) <> '' AND btrim(fiscal_address_line_1) <> '')");
        DB::statement("ALTER TABLE fiscal_issuer_profiles ADD CONSTRAINT fiscal_issuer_profiles_country_check CHECK (country_code ~ '^[A-Z]{2}$')");
        DB::statement("ALTER TABLE fiscal_issuer_profiles ADD CONSTRAINT fiscal_issuer_profiles_status_check CHECK (status IN ('ACTIVE', 'INACTIVE'))");

        Schema::create('fiscal_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->string('document_type', 20);
            $table->string('series', 40);
            $table->unsignedSmallInteger('number_padding');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('status', 16);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'document_type', 'store_id', 'tenant_id'], 'fiscal_series_id_type_store_tenant_unique');
            $table->unique(['store_id', 'document_type', 'series']);
            $table->unique(['tenant_id', 'document_type', 'series']);
            $table->index(['tenant_id', 'store_id', 'document_type', 'status'], 'fiscal_series_lookup_index');
            $table->foreign(['store_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('stores')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE fiscal_series ADD CONSTRAINT fiscal_series_type_check CHECK (document_type IN ('INVOICE', 'RECEIPT', 'CREDIT_NOTE'))");
        DB::statement("ALTER TABLE fiscal_series ADD CONSTRAINT fiscal_series_identifier_check CHECK (btrim(series) <> '' AND series = btrim(series))");
        DB::statement("ALTER TABLE fiscal_series ADD CONSTRAINT fiscal_series_status_check CHECK (status IN ('ACTIVE', 'INACTIVE'))");
        DB::statement('ALTER TABLE fiscal_series ADD CONSTRAINT fiscal_series_next_number_check CHECK (next_number > 0)');
        DB::statement('ALTER TABLE fiscal_series ADD CONSTRAINT fiscal_series_padding_check CHECK (number_padding BETWEEN 1 AND 20)');
        DB::statement("CREATE UNIQUE INDEX fiscal_series_one_active_per_store_type ON fiscal_series (store_id, document_type) WHERE status = 'ACTIVE'");
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_series');
        Schema::dropIfExists('fiscal_issuer_profiles');
    }
};
