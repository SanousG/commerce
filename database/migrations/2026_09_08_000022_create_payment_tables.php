<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_payment_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id')->unique();
            $table->boolean('bank_transfer_enabled')->default(false);
            $table->boolean('cash_enabled')->default(false);
            $table->boolean('online_enabled')->default(false);
            $table->boolean('cod_enabled')->default(false);
            $table->string('cod_deposit_type', 20)->default('NONE');
            $table->decimal('cod_deposit_value', 19, 4)->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index('tenant_id');
            $table->foreign(['store_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('stores')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE store_payment_settings ADD CONSTRAINT store_payment_settings_deposit_type_check CHECK (cod_deposit_type IN ('NONE', 'PERCENTAGE', 'FIXED_AMOUNT'))");
        DB::statement("ALTER TABLE store_payment_settings ADD CONSTRAINT store_payment_settings_deposit_check CHECK ((cod_deposit_type = 'NONE' AND cod_deposit_value = 0) OR (cod_deposit_type = 'PERCENTAGE' AND cod_deposit_value > 0 AND cod_deposit_value <= 100) OR (cod_deposit_type = 'FIXED_AMOUNT' AND cod_deposit_value > 0))");

        Schema::create('order_payment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id')->unique();
            $table->string('terms', 16);
            $table->char('currency', 3);
            $table->decimal('total_amount', 19, 4);
            $table->decimal('amount_due_now', 19, 4);
            $table->decimal('amount_due_later', 19, 4);
            $table->string('deposit_type', 20)->nullable();
            $table->decimal('deposit_value', 19, 4)->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'store_id']);
            $table->foreign(['order_id', 'store_id', 'tenant_id'])
                ->references(['id', 'store_id', 'tenant_id'])->on('orders')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE order_payment_plans ADD CONSTRAINT order_payment_plans_terms_check CHECK (terms IN ('PREPAID', 'COD'))");
        DB::statement("ALTER TABLE order_payment_plans ADD CONSTRAINT order_payment_plans_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE order_payment_plans ADD CONSTRAINT order_payment_plans_amounts_check CHECK (total_amount >= 0 AND amount_due_now >= 0 AND amount_due_later >= 0 AND amount_due_now + amount_due_later = total_amount)');
        DB::statement("ALTER TABLE order_payment_plans ADD CONSTRAINT order_payment_plans_deposit_check CHECK ((terms = 'PREPAID' AND deposit_type IS NULL AND deposit_value IS NULL AND amount_due_now = total_amount AND amount_due_later = 0) OR (terms = 'COD' AND deposit_type IN ('NONE', 'PERCENTAGE', 'FIXED_AMOUNT') AND deposit_value IS NOT NULL))");

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->string('method', 24);
            $table->string('provider', 32);
            $table->string('status', 24);
            $table->char('currency', 3);
            $table->decimal('amount', 19, 4);
            $table->string('idempotency_key', 100)->nullable();
            $table->string('external_reference', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'store_id', 'tenant_id'], 'payments_id_store_tenant_unique');
            $table->unique(['id', 'order_id', 'store_id', 'tenant_id'], 'payments_id_order_store_tenant_unique');
            $table->index(['tenant_id', 'store_id', 'created_at']);
            $table->index(['order_id', 'created_at']);
            $table->index('status');
            $table->index('method');
            $table->index('provider');
            $table->index('external_reference');
            $table->foreign(['order_id', 'store_id', 'tenant_id'])
                ->references(['id', 'store_id', 'tenant_id'])->on('orders')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('ONLINE', 'BANK_TRANSFER', 'CASH'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_check CHECK (provider IN ('MANUAL', 'NONE', 'STRIPE', 'MERCADO_PAGO', 'CULQI'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('PENDING', 'REQUIRES_ACTION', 'PROCESSING', 'AUTHORIZED', 'PARTIALLY_CAPTURED', 'CAPTURED', 'FAILED', 'CANCELLED', 'EXPIRED'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
        DB::statement('CREATE UNIQUE INDEX payments_order_idempotency_unique ON payments (order_id, idempotency_key) WHERE idempotency_key IS NOT NULL');

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->uuid('payment_id')->nullable()->unique();
            $table->string('provider', 32);
            $table->string('method', 24);
            $table->string('status', 24);
            $table->char('currency', 3);
            $table->decimal('amount', 19, 4);
            $table->string('idempotency_key', 100);
            $table->string('external_id', 255)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'payment_id', 'tenant_id'], 'payment_intents_id_payment_tenant_unique');
            $table->unique(['order_id', 'idempotency_key']);
            $table->index(['tenant_id', 'store_id']);
            $table->index(['order_id', 'created_at']);
            $table->index('status');
            $table->index('external_id');
            $table->foreign(['order_id', 'store_id', 'tenant_id'])
                ->references(['id', 'store_id', 'tenant_id'])->on('orders')->restrictOnDelete();
            $table->foreign(['payment_id', 'order_id', 'store_id', 'tenant_id'])
                ->references(['id', 'order_id', 'store_id', 'tenant_id'])->on('payments')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_method_check CHECK (method IN ('ONLINE', 'BANK_TRANSFER', 'CASH'))");
        DB::statement("ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_provider_check CHECK (provider IN ('MANUAL', 'NONE', 'STRIPE', 'MERCADO_PAGO', 'CULQI'))");
        DB::statement("ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_status_check CHECK (status IN ('PENDING', 'REQUIRES_ACTION', 'PROCESSING', 'AUTHORIZED', 'SUCCEEDED', 'FAILED', 'CANCELLED', 'EXPIRED'))");
        DB::statement("ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_amount_check CHECK (amount > 0)');
        DB::statement('CREATE UNIQUE INDEX payment_intents_provider_external_unique ON payment_intents (provider, external_id) WHERE external_id IS NOT NULL');

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('payment_id');
            $table->uuid('payment_intent_id')->nullable();
            $table->string('provider', 32);
            $table->string('status', 20);
            $table->unsignedInteger('attempt_number');
            $table->string('external_id', 255)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'payment_id', 'tenant_id'], 'payment_attempts_id_payment_tenant_unique');
            $table->unique(['payment_id', 'attempt_number']);
            $table->index(['payment_id', 'status']);
            $table->index('payment_intent_id');
            $table->index('external_id');
            $table->foreign(['payment_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('payments')->cascadeOnDelete();
            $table->foreign(['payment_intent_id', 'payment_id', 'tenant_id'])
                ->references(['id', 'payment_id', 'tenant_id'])->on('payment_intents')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_provider_check CHECK (provider IN ('MANUAL', 'NONE', 'STRIPE', 'MERCADO_PAGO', 'CULQI'))");
        DB::statement("ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_status_check CHECK (status IN ('PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED', 'CANCELLED'))");
        DB::statement('ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_number_check CHECK (attempt_number > 0)');
        DB::statement("ALTER TABLE payment_attempts ADD CONSTRAINT payment_attempts_failure_check CHECK ((status = 'FAILED') OR (failure_code IS NULL AND failure_message IS NULL))");

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('payment_id');
            $table->uuid('attempt_id')->nullable();
            $table->string('type', 16);
            $table->string('status', 16);
            $table->decimal('amount', 19, 4);
            $table->char('currency', 3);
            $table->string('external_id', 255)->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestampTz('occurred_at');
            $table->jsonb('metadata')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['id', 'tenant_id']);
            $table->index(['payment_id', 'type', 'status']);
            $table->index(['tenant_id', 'occurred_at']);
            $table->index('attempt_id');
            $table->foreign(['payment_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('payments')->restrictOnDelete();
            $table->foreign(['attempt_id', 'payment_id', 'tenant_id'])
                ->references(['id', 'payment_id', 'tenant_id'])->on('payment_attempts')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_type_check CHECK (type IN ('CAPTURE', 'AUTHORIZE', 'VOID', 'REFUND'))");
        DB::statement("ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_status_check CHECK (status IN ('PENDING', 'SUCCEEDED', 'FAILED'))");
        DB::statement("ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement('ALTER TABLE payment_transactions ADD CONSTRAINT payment_transactions_amount_check CHECK (amount > 0)');
        DB::statement('CREATE UNIQUE INDEX payment_transactions_idempotency_unique ON payment_transactions (payment_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX payment_transactions_external_unique ON payment_transactions (payment_id, external_id) WHERE external_id IS NOT NULL');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_payment_transaction_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'payment_transactions is append-only';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER payment_transactions_append_only
            BEFORE UPDATE OR DELETE ON payment_transactions
            FOR EACH ROW EXECUTE FUNCTION prevent_payment_transaction_mutation();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        DB::statement('DROP FUNCTION IF EXISTS prevent_payment_transaction_mutation()');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_payment_plans');
        Schema::dropIfExists('store_payment_settings');
    }
};
