<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unique(['id', 'order_id', 'tenant_id'], 'order_items_id_order_tenant_unique');
        });

        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id');
            $table->uuid('order_id');
            $table->uuid('fiscal_issuer_profile_id');
            $table->uuid('fiscal_series_id');
            $table->uuid('related_fiscal_document_id')->nullable();
            $table->foreignId('generated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('document_type', 20);
            $table->string('status', 20);
            $table->string('series', 40);
            $table->unsignedBigInteger('sequence_number');
            $table->string('full_number', 96);
            $table->char('currency', 3);
            $table->date('issue_date');

            $table->char('issuer_country_code', 2);
            $table->string('issuer_tax_id', 80);
            $table->string('issuer_legal_name', 200);
            $table->string('issuer_trade_name', 200)->nullable();
            $table->string('issuer_address_line_1', 255);
            $table->string('issuer_address_line_2', 255)->nullable();
            $table->string('issuer_district', 120)->nullable();
            $table->string('issuer_province', 120)->nullable();
            $table->string('issuer_region', 120)->nullable();
            $table->string('issuer_postal_code', 32)->nullable();
            $table->string('issuer_ubigeo', 20)->nullable();

            $table->string('customer_document_type', 32)->nullable();
            $table->string('customer_document_number', 80)->nullable();
            $table->string('customer_name', 200);
            $table->string('customer_address', 500)->nullable();

            $table->decimal('taxable_amount', 19, 4);
            $table->decimal('exempt_amount', 19, 4);
            $table->decimal('unaffected_amount', 19, 4);
            $table->decimal('tax_amount', 19, 4);
            $table->decimal('discount_total', 19, 4);
            $table->decimal('subtotal', 19, 4);
            $table->decimal('grand_total', 19, 4);

            $table->string('credit_note_reason_code', 40)->nullable();
            $table->string('credit_note_reason', 500)->nullable();
            $table->decimal('adjustment_amount', 19, 4)->nullable();
            $table->timestampTz('generated_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['id', 'order_id', 'tenant_id'], 'fiscal_documents_id_order_tenant_unique');
            $table->unique(['id', 'order_id', 'store_id', 'tenant_id'], 'fiscal_documents_id_order_store_tenant_unique');
            $table->unique(['tenant_id', 'full_number']);
            $table->unique(['fiscal_series_id', 'sequence_number']);
            $table->index(['tenant_id', 'store_id']);
            $table->index(['tenant_id', 'store_id', 'document_type', 'status'], 'fiscal_documents_lookup_index');
            $table->index(['tenant_id', 'store_id', 'issue_date']);
            $table->index(['tenant_id', 'customer_document_number']);
            $table->index('order_id');
            $table->foreign(['order_id', 'store_id', 'tenant_id'])
                ->references(['id', 'store_id', 'tenant_id'])->on('orders')->restrictOnDelete();
            $table->foreign(['fiscal_issuer_profile_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('fiscal_issuer_profiles')->restrictOnDelete();
            $table->foreign(['fiscal_series_id', 'document_type', 'store_id', 'tenant_id'], 'fiscal_documents_series_type_store_tenant_foreign')
                ->references(['id', 'document_type', 'store_id', 'tenant_id'])->on('fiscal_series')->restrictOnDelete();
            $table->foreign(['related_fiscal_document_id', 'order_id', 'store_id', 'tenant_id'], 'fiscal_documents_related_order_store_tenant_foreign')
                ->references(['id', 'order_id', 'store_id', 'tenant_id'])->on('fiscal_documents')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE fiscal_documents ADD CONSTRAINT fiscal_documents_type_check CHECK (document_type IN ('INVOICE', 'RECEIPT', 'CREDIT_NOTE'))");
        DB::statement("ALTER TABLE fiscal_documents ADD CONSTRAINT fiscal_documents_status_check CHECK (status IN ('DRAFT', 'GENERATED', 'SUBMITTED', 'ACCEPTED', 'REJECTED', 'CANCELLED'))");
        DB::statement("ALTER TABLE fiscal_documents ADD CONSTRAINT fiscal_documents_values_check CHECK (btrim(series) <> '' AND sequence_number > 0 AND btrim(full_number) <> '' AND btrim(issuer_tax_id) <> '' AND btrim(issuer_legal_name) <> '' AND btrim(issuer_address_line_1) <> '' AND btrim(customer_name) <> '')");
        DB::statement("ALTER TABLE fiscal_documents ADD CONSTRAINT fiscal_documents_codes_check CHECK (currency ~ '^[A-Z]{3}$' AND issuer_country_code ~ '^[A-Z]{2}$')");
        DB::statement('ALTER TABLE fiscal_documents ADD CONSTRAINT fiscal_documents_money_check CHECK (taxable_amount >= 0 AND exempt_amount >= 0 AND unaffected_amount >= 0 AND tax_amount >= 0 AND discount_total >= 0 AND subtotal >= 0 AND grand_total >= 0 AND taxable_amount + exempt_amount + unaffected_amount + tax_amount = subtotal AND subtotal = grand_total)');
        DB::statement("ALTER TABLE fiscal_documents ADD CONSTRAINT fiscal_documents_credit_note_check CHECK ((document_type = 'CREDIT_NOTE' AND related_fiscal_document_id IS NOT NULL AND related_fiscal_document_id <> id AND btrim(credit_note_reason_code) <> '' AND btrim(credit_note_reason) <> '' AND adjustment_amount IS NOT NULL AND adjustment_amount > 0) OR (document_type <> 'CREDIT_NOTE' AND related_fiscal_document_id IS NULL AND credit_note_reason_code IS NULL AND credit_note_reason IS NULL AND adjustment_amount IS NULL))");
        DB::statement('ALTER TABLE fiscal_documents ADD CONSTRAINT fiscal_documents_customer_identity_check CHECK ((customer_document_type IS NULL) = (customer_document_number IS NULL))');
        DB::statement("CREATE UNIQUE INDEX fiscal_documents_primary_order_unique ON fiscal_documents (order_id) WHERE document_type IN ('INVOICE', 'RECEIPT')");

        Schema::create('fiscal_document_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('order_id');
            $table->uuid('fiscal_document_id');
            $table->uuid('order_item_id')->nullable();
            $table->unsignedInteger('position');
            $table->string('description', 255);
            $table->string('sku', 100)->nullable();
            $table->decimal('quantity', 19, 4);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('base_amount', 19, 4);
            $table->decimal('discount_amount', 19, 4);
            $table->string('tax_category_code', 20);
            $table->decimal('tax_rate', 9, 6);
            $table->decimal('tax_amount', 19, 4);
            $table->decimal('line_total', 19, 4);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['fiscal_document_id', 'position']);
            $table->index(['tenant_id', 'order_id']);
            $table->index('order_item_id');
            $table->foreign(['fiscal_document_id', 'order_id', 'tenant_id'])
                ->references(['id', 'order_id', 'tenant_id'])->on('fiscal_documents')->restrictOnDelete();
            $table->foreign(['order_item_id', 'order_id', 'tenant_id'])
                ->references(['id', 'order_id', 'tenant_id'])->on('order_items')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE fiscal_document_items ADD CONSTRAINT fiscal_document_items_values_check CHECK (position > 0 AND btrim(description) <> '' AND quantity > 0 AND unit_price >= 0 AND base_amount >= 0 AND discount_amount >= 0 AND tax_amount >= 0 AND line_total >= 0)");
        DB::statement("ALTER TABLE fiscal_document_items ADD CONSTRAINT fiscal_document_items_tax_category_check CHECK (tax_category_code IN ('STANDARD', 'EXEMPT', 'UNAFFECTED'))");
        DB::statement('ALTER TABLE fiscal_document_items ADD CONSTRAINT fiscal_document_items_tax_rate_check CHECK (tax_rate >= 0 AND tax_rate <= 100)');
        DB::statement('ALTER TABLE fiscal_document_items ADD CONSTRAINT fiscal_document_items_total_check CHECK (unit_price * quantity - discount_amount = line_total AND base_amount + tax_amount = line_total)');

        DB::statement('DROP FUNCTION IF EXISTS prevent_fiscal_document_item_mutation() CASCADE');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION prevent_fiscal_document_item_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'fiscal_document_items are immutable snapshots';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fiscal_document_items_immutable
            BEFORE UPDATE OR DELETE ON fiscal_document_items
            FOR EACH ROW EXECUTE FUNCTION prevent_fiscal_document_item_mutation();
        SQL);
        DB::statement('DROP FUNCTION IF EXISTS protect_fiscal_document_snapshot() CASCADE');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION protect_fiscal_document_snapshot() RETURNS trigger AS $$
            BEGIN
                IF ROW(NEW.tenant_id, NEW.store_id, NEW.order_id, NEW.fiscal_issuer_profile_id, NEW.fiscal_series_id, NEW.generated_by_user_id,
                    NEW.related_fiscal_document_id, NEW.document_type, NEW.series, NEW.sequence_number, NEW.full_number,
                    NEW.currency, NEW.issue_date, NEW.issuer_country_code, NEW.issuer_tax_id, NEW.issuer_legal_name,
                    NEW.issuer_trade_name, NEW.issuer_address_line_1, NEW.issuer_address_line_2, NEW.issuer_district,
                    NEW.issuer_province, NEW.issuer_region, NEW.issuer_postal_code, NEW.issuer_ubigeo,
                    NEW.customer_document_type, NEW.customer_document_number, NEW.customer_name, NEW.customer_address,
                    NEW.taxable_amount, NEW.exempt_amount, NEW.unaffected_amount, NEW.tax_amount, NEW.discount_total,
                    NEW.subtotal, NEW.grand_total, NEW.credit_note_reason_code, NEW.credit_note_reason, NEW.adjustment_amount)
                IS DISTINCT FROM
                   ROW(OLD.tenant_id, OLD.store_id, OLD.order_id, OLD.fiscal_issuer_profile_id, OLD.fiscal_series_id, OLD.generated_by_user_id,
                    OLD.related_fiscal_document_id, OLD.document_type, OLD.series, OLD.sequence_number, OLD.full_number,
                    OLD.currency, OLD.issue_date, OLD.issuer_country_code, OLD.issuer_tax_id, OLD.issuer_legal_name,
                    OLD.issuer_trade_name, OLD.issuer_address_line_1, OLD.issuer_address_line_2, OLD.issuer_district,
                    OLD.issuer_province, OLD.issuer_region, OLD.issuer_postal_code, OLD.issuer_ubigeo,
                    OLD.customer_document_type, OLD.customer_document_number, OLD.customer_name, OLD.customer_address,
                    OLD.taxable_amount, OLD.exempt_amount, OLD.unaffected_amount, OLD.tax_amount, OLD.discount_total,
                    OLD.subtotal, OLD.grand_total, OLD.credit_note_reason_code, OLD.credit_note_reason, OLD.adjustment_amount) THEN
                    RAISE EXCEPTION 'fiscal document snapshot fields are immutable';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fiscal_documents_snapshot_immutable
            BEFORE UPDATE ON fiscal_documents
            FOR EACH ROW EXECUTE FUNCTION protect_fiscal_document_snapshot();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS fiscal_document_items_immutable ON fiscal_document_items');
        DB::statement('DROP FUNCTION IF EXISTS prevent_fiscal_document_item_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS fiscal_documents_snapshot_immutable ON fiscal_documents');
        DB::statement('DROP FUNCTION IF EXISTS protect_fiscal_document_snapshot()');
        Schema::dropIfExists('fiscal_document_items');
        Schema::dropIfExists('fiscal_documents');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique('order_items_id_order_tenant_unique');
        });
    }
};
