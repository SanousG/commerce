<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds per-user notification preferences, an email delivery marker, and the
     * operational event vocabulary introduced by the notifications phase.
     */
    public function up(): void
    {
        Schema::create('admin_notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignId('user_id');
            $table->boolean('email_enabled')->default(false);
            $table->jsonb('muted_types')->default('[]');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'user_id'], 'admin_notification_preferences_member_unique');
            $table->foreign(['tenant_id', 'user_id'], 'admin_notification_preferences_membership_fk')
                ->references(['tenant_id', 'user_id'])->on('tenant_members')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE admin_notification_preferences ADD CONSTRAINT admin_notification_preferences_muted_types_check CHECK (jsonb_typeof(muted_types) = 'array')");

        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->timestampTz('emailed_at')->nullable()->after('resolved_at');
        });

        DB::statement('ALTER TABLE admin_notifications DROP CONSTRAINT admin_notifications_type_check');
        DB::statement("ALTER TABLE admin_notifications ADD CONSTRAINT admin_notifications_type_check CHECK (type IN ('LOW_STOCK','OUT_OF_STOCK','PAYMENT_PENDING','PAYMENT_CAPTURED','REFUND_PENDING','REFUND_COMPLETED','RETURN_REQUESTED','RETURN_APPROVED','RETURN_AWAITING_INSPECTION','FULFILLMENT_COMPLETED','SHIPMENT_READY','PURCHASE_ORDER_TO_RECEIVE','PURCHASE_RECEIPT_POSTED','ORDER_PLACED','FISCAL_REJECTED','FISCAL_UNKNOWN','TEAM_INVITATION'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE admin_notifications DROP CONSTRAINT admin_notifications_type_check');
        DB::statement("ALTER TABLE admin_notifications ADD CONSTRAINT admin_notifications_type_check CHECK (type IN ('LOW_STOCK','OUT_OF_STOCK','PAYMENT_PENDING','REFUND_PENDING','RETURN_AWAITING_INSPECTION','PURCHASE_ORDER_TO_RECEIVE','FISCAL_REJECTED','FISCAL_UNKNOWN','TEAM_INVITATION'))");

        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropColumn('emailed_at');
        });

        Schema::dropIfExists('admin_notification_preferences');
    }
};
