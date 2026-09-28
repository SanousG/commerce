<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->uuid('payment_id');
            $table->uuid('return_id')->nullable();
            $table->string('status', 16);
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('reason', 500);
            $table->string('external_reference', 255)->nullable();
            $table->string('idempotency_key', 100);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'payment_id', 'tenant_id']);
            $table->unique(['payment_id', 'idempotency_key']);
            $table->index(['tenant_id', 'store_id', 'created_at']);
            $table->index(['order_id', 'status']);
            $table->index(['payment_id', 'status']);
            $table->foreign(['payment_id', 'order_id', 'store_id', 'tenant_id'])->references(['id', 'order_id', 'store_id', 'tenant_id'])->on('payments')->restrictOnDelete();
            $table->foreign(['return_id', 'order_id', 'store_id', 'tenant_id'])->references(['id', 'order_id', 'store_id', 'tenant_id'])->on('returns')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_status_check CHECK (status IN ('PENDING','COMPLETED','FAILED','CANCELLED'))");
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT refunds_completion_check CHECK ((status = 'COMPLETED' AND completed_at IS NOT NULL) OR (status <> 'COMPLETED' AND completed_at IS NULL))");

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->uuid('refund_id')->nullable()->after('attempt_id');
            $table->unique('refund_id');
            $table->foreign(['refund_id', 'payment_id', 'tenant_id'])->references(['id', 'payment_id', 'tenant_id'])->on('refunds')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_refund_link_check CHECK ((type = 'REFUND') = (refund_id IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropForeign(['refund_id', 'payment_id', 'tenant_id']);
            $table->dropUnique(['refund_id']);
            $table->dropColumn('refund_id');
        });
        Schema::dropIfExists('refunds');
    }
};
