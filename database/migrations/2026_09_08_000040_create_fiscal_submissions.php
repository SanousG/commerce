<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('fiscal_document_id');
            $table->unsignedInteger('attempt_number');
            $table->string('status', 20);
            $table->string('provider', 80);
            $table->string('request_hash', 128)->nullable();
            $table->string('external_ticket', 255)->nullable();
            $table->string('response_code', 100)->nullable();
            $table->text('response_message')->nullable();
            $table->string('cdr_path', 500)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['fiscal_document_id', 'attempt_number']);
            $table->index(['fiscal_document_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->foreign(['fiscal_document_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('fiscal_documents')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE fiscal_submissions ADD CONSTRAINT fiscal_submissions_status_check CHECK (status IN ('PENDING', 'SENT', 'ACCEPTED', 'REJECTED', 'ERROR'))");
        DB::statement("ALTER TABLE fiscal_submissions ADD CONSTRAINT fiscal_submissions_values_check CHECK (attempt_number > 0 AND btrim(provider) <> '')");
        DB::statement('ALTER TABLE fiscal_submissions ADD CONSTRAINT fiscal_submissions_dates_check CHECK (responded_at IS NULL OR (submitted_at IS NOT NULL AND responded_at >= submitted_at))');
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_submissions');
    }
};
