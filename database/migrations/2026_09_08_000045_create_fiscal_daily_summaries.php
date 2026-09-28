<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->unique(['id', 'store_id', 'tenant_id'], 'fiscal_documents_id_store_tenant_unique');
        });

        Schema::create('fiscal_daily_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->date('summary_date');
            $table->unsignedInteger('sequence');
            $table->string('status', 20);
            $table->string('identifier', 40);
            $table->string('environment', 16);
            $table->string('ticket', 255)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id', 'store_id'], 'fiscal_daily_summaries_id_tenant_store_unique');
            $table->unique(['tenant_id', 'summary_date', 'sequence']);
            $table->unique(['tenant_id', 'identifier']);
            $table->index(['tenant_id', 'store_id', 'summary_date']);
            $table->foreign(['store_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('stores')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE fiscal_daily_summaries ADD CONSTRAINT fiscal_daily_summaries_status_check CHECK (status IN ('DRAFT', 'SUBMITTED', 'PROCESSING', 'ACCEPTED', 'REJECTED', 'ERROR', 'UNKNOWN'))");
        DB::statement("ALTER TABLE fiscal_daily_summaries ADD CONSTRAINT fiscal_daily_summaries_environment_check CHECK (environment IN ('BETA', 'PRODUCTION'))");
        DB::statement("ALTER TABLE fiscal_daily_summaries ADD CONSTRAINT fiscal_daily_summaries_values_check CHECK (sequence > 0 AND identifier ~ '^RC-[0-9]{8}-[0-9]+$')");

        Schema::create('fiscal_daily_summary_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('fiscal_daily_summary_id');
            $table->uuid('fiscal_document_id');
            $table->string('status', 16)->default('INCLUDED');
            $table->timestamps();

            $table->unique(['fiscal_daily_summary_id', 'fiscal_document_id']);
            $table->foreign(['fiscal_daily_summary_id', 'tenant_id', 'store_id'], 'fiscal_daily_summary_items_summary_foreign')
                ->references(['id', 'tenant_id', 'store_id'])->on('fiscal_daily_summaries')->cascadeOnDelete();
            $table->foreign(['fiscal_document_id', 'store_id', 'tenant_id'], 'fiscal_daily_summary_items_document_foreign')
                ->references(['id', 'store_id', 'tenant_id'])->on('fiscal_documents')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE fiscal_daily_summary_items ADD CONSTRAINT fiscal_daily_summary_items_status_check CHECK (status IN ('INCLUDED', 'REMOVED'))");
        DB::statement("CREATE UNIQUE INDEX fiscal_daily_summary_items_one_active ON fiscal_daily_summary_items (fiscal_document_id) WHERE status = 'INCLUDED'");
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_daily_summary_items');
        Schema::dropIfExists('fiscal_daily_summaries');
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->dropUnique('fiscal_documents_id_store_tenant_unique');
        });
    }
};
