<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_status_check CHECK (status IN ('ACTIVE', 'INACTIVE', 'BLOCKED', 'ARCHIVED'))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_identity_check CHECK (nullif(btrim(first_name), '') IS NOT NULL OR nullif(btrim(last_name), '') IS NOT NULL OR email IS NOT NULL OR phone IS NOT NULL)");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_email_normalized_check CHECK (email IS NULL OR (email = lower(btrim(email)) AND email <> ''))");
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_phone_normalized_check CHECK (phone IS NULL OR phone ~ '^\\+?[0-9]{6,31}$')");
        DB::statement('CREATE UNIQUE INDEX customers_tenant_email_unique ON customers (tenant_id, lower(email)) WHERE email IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX customers_tenant_phone_unique ON customers (tenant_id, phone) WHERE phone IS NOT NULL');

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('customer_id');
            $table->string('label', 80)->nullable();
            $table->string('recipient_name', 200)->nullable();
            $table->string('phone', 32)->nullable();
            $table->char('country_code', 2);
            $table->string('region', 120)->nullable();
            $table->string('city', 120);
            $table->string('district', 120)->nullable();
            $table->string('address_line_1', 240);
            $table->string('address_line_2', 240)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('reference', 500)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('customer_id');
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['customer_id', 'is_default_shipping']);
            $table->index(['customer_id', 'is_default_billing']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(['customer_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('customers')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE customer_addresses ADD CONSTRAINT customer_addresses_country_code_check CHECK (country_code ~ '^[A-Z]{2}$')");
        DB::statement("ALTER TABLE customer_addresses ADD CONSTRAINT customer_addresses_city_check CHECK (btrim(city) <> '')");
        DB::statement("ALTER TABLE customer_addresses ADD CONSTRAINT customer_addresses_line_1_check CHECK (btrim(address_line_1) <> '')");
        DB::statement("ALTER TABLE customer_addresses ADD CONSTRAINT customer_addresses_phone_normalized_check CHECK (phone IS NULL OR phone ~ '^\\+?[0-9]{6,31}$')");
        DB::statement('CREATE UNIQUE INDEX customer_addresses_default_shipping_unique ON customer_addresses (customer_id) WHERE is_default_shipping');
        DB::statement('CREATE UNIQUE INDEX customer_addresses_default_billing_unique ON customer_addresses (customer_id) WHERE is_default_billing');
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
