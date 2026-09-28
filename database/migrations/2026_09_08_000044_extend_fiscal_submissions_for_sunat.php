<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_submissions', function (Blueprint $table) {
            $table->string('environment', 16)->nullable();
            $table->string('request_storage_key', 500)->nullable();
            $table->string('unsigned_xml_storage_key', 500)->nullable();
            $table->string('signed_xml_storage_key', 500)->nullable();
            $table->string('zip_storage_key', 500)->nullable();
            $table->string('cdr_storage_key', 500)->nullable();
            $table->string('cdr_xml_storage_key', 500)->nullable();
            $table->text('response_description')->nullable();
            $table->jsonb('observations')->nullable();
            $table->string('error_class', 120)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
        });

        DB::statement('ALTER TABLE fiscal_submissions DROP CONSTRAINT fiscal_submissions_status_check');
        DB::statement("ALTER TABLE fiscal_submissions ADD CONSTRAINT fiscal_submissions_status_check CHECK (status IN ('PENDING', 'PROCESSING', 'SENT', 'ACCEPTED', 'REJECTED', 'ERROR', 'UNKNOWN'))");
        DB::statement("ALTER TABLE fiscal_submissions ADD CONSTRAINT fiscal_submissions_environment_check CHECK (environment IS NULL OR environment IN ('BETA', 'PRODUCTION'))");
        DB::statement("CREATE UNIQUE INDEX fiscal_submissions_one_in_flight ON fiscal_submissions (fiscal_document_id) WHERE status IN ('PENDING', 'PROCESSING', 'SENT', 'UNKNOWN')");

        DB::statement('ALTER TABLE fiscal_documents DROP CONSTRAINT fiscal_documents_status_check');
        DB::statement("ALTER TABLE fiscal_documents ADD CONSTRAINT fiscal_documents_status_check CHECK (status IN ('DRAFT', 'GENERATED', 'SUBMISSION_PENDING', 'SUBMITTED', 'ACCEPTED', 'REJECTED', 'CANCELLED'))");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS fiscal_submissions_one_in_flight');
        DB::statement('ALTER TABLE fiscal_submissions DROP CONSTRAINT fiscal_submissions_environment_check');
        DB::statement('ALTER TABLE fiscal_submissions DROP CONSTRAINT fiscal_submissions_status_check');
        DB::statement("ALTER TABLE fiscal_submissions ADD CONSTRAINT fiscal_submissions_status_check CHECK (status IN ('PENDING', 'SENT', 'ACCEPTED', 'REJECTED', 'ERROR'))");
        DB::statement('ALTER TABLE fiscal_documents DROP CONSTRAINT fiscal_documents_status_check');
        DB::statement("ALTER TABLE fiscal_documents ADD CONSTRAINT fiscal_documents_status_check CHECK (status IN ('DRAFT', 'GENERATED', 'SUBMITTED', 'ACCEPTED', 'REJECTED', 'CANCELLED'))");
        Schema::table('fiscal_submissions', function (Blueprint $table) {
            $table->dropColumn(['environment', 'request_storage_key', 'unsigned_xml_storage_key', 'signed_xml_storage_key', 'zip_storage_key', 'cdr_storage_key', 'cdr_xml_storage_key', 'response_description', 'observations', 'error_class', 'duration_ms']);
        });
    }
};
