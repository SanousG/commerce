<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_document_items', function (Blueprint $table) {
            $table->decimal('taxable_amount', 19, 4)->nullable();
            $table->decimal('exempt_amount', 19, 4)->nullable();
            $table->decimal('unaffected_amount', 19, 4)->nullable();
        });
        DB::statement('ALTER TABLE fiscal_document_items ADD CONSTRAINT fiscal_document_items_tax_buckets_check CHECK ((taxable_amount IS NULL AND exempt_amount IS NULL AND unaffected_amount IS NULL) OR (taxable_amount IS NOT NULL AND exempt_amount IS NOT NULL AND unaffected_amount IS NOT NULL AND taxable_amount >= 0 AND exempt_amount >= 0 AND unaffected_amount >= 0 AND taxable_amount + exempt_amount + unaffected_amount = base_amount))');
        DB::statement("ALTER TABLE fiscal_document_items ADD CONSTRAINT fiscal_document_items_tax_bucket_category_check CHECK (taxable_amount IS NULL OR (tax_category_code = 'STANDARD' AND exempt_amount = 0 AND unaffected_amount = 0 AND taxable_amount = base_amount) OR (tax_category_code = 'EXEMPT' AND tax_rate = 0 AND taxable_amount = 0 AND unaffected_amount = 0 AND tax_amount = 0 AND exempt_amount = base_amount) OR (tax_category_code = 'UNAFFECTED' AND tax_rate = 0 AND taxable_amount = 0 AND exempt_amount = 0 AND tax_amount = 0 AND unaffected_amount = base_amount))");
        DB::statement('DROP FUNCTION IF EXISTS prevent_fiscal_document_item_tax_bucket_mutation() CASCADE');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION prevent_fiscal_document_item_tax_bucket_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'fiscal document item tax buckets are immutable snapshots';
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER fiscal_document_items_tax_buckets_immutable
            BEFORE UPDATE OF taxable_amount, exempt_amount, unaffected_amount ON fiscal_document_items
            FOR EACH ROW EXECUTE FUNCTION prevent_fiscal_document_item_tax_bucket_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS fiscal_document_items_tax_buckets_immutable ON fiscal_document_items');
        DB::statement('DROP FUNCTION IF EXISTS prevent_fiscal_document_item_tax_bucket_mutation()');
        DB::statement('ALTER TABLE fiscal_document_items DROP CONSTRAINT IF EXISTS fiscal_document_items_tax_bucket_category_check');
        DB::statement('ALTER TABLE fiscal_document_items DROP CONSTRAINT IF EXISTS fiscal_document_items_tax_buckets_check');
        Schema::table('fiscal_document_items', function (Blueprint $table) {
            $table->dropColumn(['taxable_amount', 'exempt_amount', 'unaffected_amount']);
        });
    }
};
