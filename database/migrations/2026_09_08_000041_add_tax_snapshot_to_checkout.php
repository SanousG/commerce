<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('tax_snapshot_version')->nullable();
            $table->decimal('taxable_amount', 19, 4)->nullable();
            $table->decimal('exempt_amount', 19, 4)->nullable();
            $table->decimal('unaffected_amount', 19, 4)->nullable();
            $table->timestampTz('tax_calculated_at')->nullable();
        });
        Schema::table('checkout_lines', function (Blueprint $table) {
            $table->string('tax_category_code', 20)->nullable();
            $table->decimal('tax_rate', 9, 6)->nullable();
            $table->decimal('taxable_amount', 19, 4)->nullable();
            $table->decimal('exempt_amount', 19, 4)->nullable();
            $table->decimal('unaffected_amount', 19, 4)->nullable();
            $table->decimal('tax_amount', 19, 4)->nullable();
        });

        DB::statement('ALTER TABLE checkout_sessions DROP CONSTRAINT checkout_sessions_totals_check');
        DB::statement('ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_totals_check CHECK (subtotal_before_discounts - discount_total = subtotal AND ((tax_snapshot_version IS NULL AND subtotal + shipping_total + tax_total = grand_total) OR (tax_snapshot_version = 1 AND subtotal + shipping_total = grand_total)))');
        DB::statement('ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_tax_snapshot_check CHECK ((tax_snapshot_version IS NULL AND taxable_amount IS NULL AND exempt_amount IS NULL AND unaffected_amount IS NULL AND tax_calculated_at IS NULL) OR (tax_snapshot_version = 1 AND taxable_amount >= 0 AND exempt_amount >= 0 AND unaffected_amount >= 0 AND tax_calculated_at IS NOT NULL AND shipping_total = 0 AND taxable_amount + exempt_amount + unaffected_amount + tax_total = subtotal))');
        DB::statement('ALTER TABLE checkout_lines ADD CONSTRAINT checkout_lines_tax_snapshot_completeness_check CHECK ((tax_category_code IS NULL AND tax_rate IS NULL AND taxable_amount IS NULL AND exempt_amount IS NULL AND unaffected_amount IS NULL AND tax_amount IS NULL) OR (tax_category_code IS NOT NULL AND tax_rate IS NOT NULL AND taxable_amount IS NOT NULL AND exempt_amount IS NOT NULL AND unaffected_amount IS NOT NULL AND tax_amount IS NOT NULL))');
        DB::statement("ALTER TABLE checkout_lines ADD CONSTRAINT checkout_lines_tax_snapshot_values_check CHECK (tax_category_code IS NULL OR (tax_category_code IN ('STANDARD', 'EXEMPT', 'UNAFFECTED') AND tax_rate >= 0 AND tax_rate <= 100 AND taxable_amount >= 0 AND exempt_amount >= 0 AND unaffected_amount >= 0 AND tax_amount >= 0))");
        DB::statement("ALTER TABLE checkout_lines ADD CONSTRAINT checkout_lines_tax_snapshot_buckets_check CHECK (tax_category_code IS NULL OR (tax_category_code = 'STANDARD' AND exempt_amount = 0 AND unaffected_amount = 0 AND taxable_amount + tax_amount = line_total) OR (tax_category_code = 'EXEMPT' AND tax_rate = 0 AND taxable_amount = 0 AND unaffected_amount = 0 AND tax_amount = 0 AND exempt_amount = line_total) OR (tax_category_code = 'UNAFFECTED' AND tax_rate = 0 AND taxable_amount = 0 AND exempt_amount = 0 AND tax_amount = 0 AND unaffected_amount = line_total))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE checkout_lines DROP CONSTRAINT IF EXISTS checkout_lines_tax_snapshot_buckets_check');
        DB::statement('ALTER TABLE checkout_lines DROP CONSTRAINT IF EXISTS checkout_lines_tax_snapshot_values_check');
        DB::statement('ALTER TABLE checkout_lines DROP CONSTRAINT IF EXISTS checkout_lines_tax_snapshot_completeness_check');
        DB::statement('ALTER TABLE checkout_sessions DROP CONSTRAINT IF EXISTS checkout_sessions_tax_snapshot_check');
        DB::statement('ALTER TABLE checkout_sessions DROP CONSTRAINT checkout_sessions_totals_check');
        DB::statement('UPDATE checkout_sessions SET tax_total = 0 WHERE tax_snapshot_version = 1');
        DB::statement('ALTER TABLE checkout_sessions ADD CONSTRAINT checkout_sessions_totals_check CHECK (subtotal_before_discounts - discount_total = subtotal AND subtotal + shipping_total + tax_total = grand_total)');
        Schema::table('checkout_lines', function (Blueprint $table) {
            $table->dropColumn(['tax_category_code', 'tax_rate', 'taxable_amount', 'exempt_amount', 'unaffected_amount', 'tax_amount']);
        });
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropColumn(['tax_snapshot_version', 'taxable_amount', 'exempt_amount', 'unaffected_amount', 'tax_calculated_at']);
        });
    }
};
