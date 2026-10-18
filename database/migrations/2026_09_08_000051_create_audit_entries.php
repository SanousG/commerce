<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('store_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('actor_type', ['USER', 'SYSTEM']);
            $table->string('action', 100);
            $table->string('subject_type', 64);
            $table->string('subject_id', 255)->nullable();
            $table->string('subject_label', 255)->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->timestampTz('occurred_at');
            $table->uuid('correlation_id');
            $table->string('idempotency_key', 100)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['tenant_id', 'occurred_at', 'id'], 'audit_entries_tenant_timeline_index');
            $table->index(['tenant_id', 'action', 'occurred_at'], 'audit_entries_tenant_action_index');
            $table->index(['tenant_id', 'subject_type', 'subject_id', 'occurred_at'], 'audit_entries_tenant_subject_index');
            $table->index(['tenant_id', 'actor_user_id', 'occurred_at'], 'audit_entries_tenant_actor_index');
            $table->index(['tenant_id', 'store_id', 'occurred_at'], 'audit_entries_tenant_store_index');
            $table->index(['tenant_id', 'correlation_id'], 'audit_entries_tenant_correlation_index');
            $table->foreign(['store_id', 'tenant_id'], 'audit_entries_store_fk')
                ->references(['id', 'tenant_id'])->on('stores')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX audit_entries_tenant_idempotency_unique ON audit_entries (tenant_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE audit_entries ADD CONSTRAINT audit_entries_actor_consistency_check
            CHECK (actor_type = 'USER' OR (actor_type = 'SYSTEM' AND actor_user_id IS NULL))
        SQL);
        DB::statement("ALTER TABLE audit_entries ADD CONSTRAINT audit_entries_subject_id_check CHECK (subject_id IS NULL OR btrim(subject_id) <> '')");
        DB::statement("ALTER TABLE audit_entries ADD CONSTRAINT audit_entries_subject_label_check CHECK (subject_label IS NULL OR btrim(subject_label) <> '')");
        DB::statement("ALTER TABLE audit_entries ADD CONSTRAINT audit_entries_idempotency_check CHECK (idempotency_key IS NULL OR btrim(idempotency_key) <> '')");
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_audit_entry_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit entries are append-only';
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_entries_append_only
            BEFORE UPDATE OR DELETE ON audit_entries
            FOR EACH ROW EXECUTE FUNCTION prevent_audit_entry_mutation()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_entries_append_only ON audit_entries');
        Schema::dropIfExists('audit_entries');
        DB::statement('DROP FUNCTION IF EXISTS prevent_audit_entry_mutation()');
    }
};
