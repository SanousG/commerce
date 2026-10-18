<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id')->nullable();
            $table->foreignId('recipient_user_id');
            $table->string('type', 48);
            $table->string('severity', 16);
            $table->string('title', 180);
            $table->text('message');
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 255)->nullable();
            $table->string('action_url', 500)->nullable();
            $table->string('deduplication_key', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('dismissed_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'recipient_user_id', 'created_at'], 'admin_notifications_recipient_index');
            $table->index(['tenant_id', 'recipient_user_id', 'store_id', 'read_at'], 'admin_notifications_unread_index');
            $table->index(['tenant_id', 'type', 'resolved_at'], 'admin_notifications_type_index');
            $table->index(['tenant_id', 'subject_type', 'subject_id'], 'admin_notifications_subject_index');
            $table->foreign(['tenant_id', 'recipient_user_id'], 'admin_notifications_membership_fk')
                ->references(['tenant_id', 'user_id'])->on('tenant_members')->restrictOnDelete();
            $table->foreign(['store_id', 'tenant_id'], 'admin_notifications_store_fk')
                ->references(['id', 'tenant_id'])->on('stores')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE admin_notifications ADD CONSTRAINT admin_notifications_type_check CHECK (type IN ('LOW_STOCK','OUT_OF_STOCK','PAYMENT_PENDING','REFUND_PENDING','RETURN_AWAITING_INSPECTION','PURCHASE_ORDER_TO_RECEIVE','FISCAL_REJECTED','FISCAL_UNKNOWN','TEAM_INVITATION'))");
        DB::statement("ALTER TABLE admin_notifications ADD CONSTRAINT admin_notifications_severity_check CHECK (severity IN ('INFO','WARNING','CRITICAL'))");
        DB::statement("ALTER TABLE admin_notifications ADD CONSTRAINT admin_notifications_content_check CHECK (btrim(title) <> '' AND btrim(message) <> '' AND (deduplication_key IS NULL OR btrim(deduplication_key) <> ''))");
        DB::statement("ALTER TABLE admin_notifications ADD CONSTRAINT admin_notifications_action_url_check CHECK (action_url IS NULL OR action_url = '/admin' OR action_url LIKE '/admin/%' OR action_url LIKE '/admin?%')");
        DB::statement('CREATE UNIQUE INDEX admin_notifications_active_dedupe_unique ON admin_notifications (tenant_id, recipient_user_id, deduplication_key) WHERE resolved_at IS NULL AND dismissed_at IS NULL AND deduplication_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
