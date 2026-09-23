<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 40);
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
        });
        DB::statement("ALTER TABLE price_lists ADD CONSTRAINT price_lists_status_check CHECK (status IN ('ACTIVE', 'INACTIVE', 'ARCHIVED'))");
        DB::statement("ALTER TABLE price_lists ADD CONSTRAINT price_lists_currency_check CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE price_lists ADD CONSTRAINT price_lists_default_active_check CHECK (NOT is_default OR status = 'ACTIVE')");
        DB::statement('CREATE UNIQUE INDEX price_lists_tenant_code_unique ON price_lists (tenant_id, upper(code))');
        DB::statement('CREATE UNIQUE INDEX price_lists_one_default_unique ON price_lists (tenant_id) WHERE is_default');

        $this->backfillDefaultPriceLists();
        $this->createDefaultPriceListConstraint();

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['id', 'tenant_id'], 'product_variants_id_tenant_unique');
        });

        Schema::create('variant_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('price_list_id');
            $table->uuid('product_variant_id');
            $table->decimal('amount', 19, 4);
            $table->timestamps();

            $table->unique(['price_list_id', 'product_variant_id']);
            $table->index(['tenant_id', 'product_variant_id']);
            $table->index(['product_variant_id']);
            $table->foreign(['price_list_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('price_lists')->cascadeOnDelete();
            $table->foreign(['product_variant_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('product_variants')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE variant_prices ADD CONSTRAINT variant_prices_amount_check CHECK (amount >= 0 AND amount::text NOT IN ('NaN', 'Infinity', '-Infinity'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_prices');
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique('product_variants_id_tenant_unique');
        });
        DB::statement('DROP TRIGGER tenants_default_price_list_check ON tenants');
        DB::statement('DROP TRIGGER price_lists_default_check ON price_lists');
        DB::statement('DROP FUNCTION enforce_tenant_default_price_list()');
        Schema::dropIfExists('price_lists');
    }

    private function backfillDefaultPriceLists(): void
    {
        $currency = str((string) config('commerce.default_currency', 'PEN'))->trim()->upper()->toString();
        if (! in_array($currency, config('commerce.currencies', []), true)) {
            throw new RuntimeException('The configured default commerce currency is unsupported.');
        }

        DB::statement(<<<'SQL'
            INSERT INTO price_lists (id, tenant_id, name, code, currency, status, is_default, created_at, updated_at)
            SELECT (
                '01994000-0000-7' || substr(md5(tenants.id::text || ':default-price-list'), 1, 3) ||
                '-8' || substr(md5(tenants.id::text || ':default-price-list'), 4, 3) ||
                '-' || substr(md5(tenants.id::text || ':default-price-list'), 7, 12)
            )::uuid,
            tenants.id, 'Precio principal', 'DEFAULT', CAST(? AS char(3)), 'ACTIVE', true,
            CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM tenants
            WHERE NOT EXISTS (
                SELECT 1 FROM price_lists WHERE price_lists.tenant_id = tenants.id AND price_lists.is_default
            )
        SQL, [$currency]);
    }

    private function createDefaultPriceListConstraint(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS enforce_tenant_default_price_list() CASCADE');
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION enforce_tenant_default_price_list() RETURNS trigger AS $$
            DECLARE
                checked_tenant_id uuid;
            BEGIN
                IF TG_TABLE_NAME = 'tenants' THEN
                    checked_tenant_id := NEW.id;
                ELSIF TG_OP = 'DELETE' THEN
                    checked_tenant_id := OLD.tenant_id;
                ELSE
                    checked_tenant_id := NEW.tenant_id;
                END IF;

                IF EXISTS (SELECT 1 FROM tenants WHERE id = checked_tenant_id)
                   AND (SELECT COUNT(*) FROM price_lists WHERE tenant_id = checked_tenant_id AND is_default AND status = 'ACTIVE') <> 1 THEN
                    RAISE EXCEPTION 'A tenant requires exactly one active default price list' USING ERRCODE = '23514';
                END IF;

                IF TG_TABLE_NAME = 'price_lists' AND TG_OP = 'UPDATE' THEN
                    IF OLD.tenant_id IS DISTINCT FROM NEW.tenant_id
                       AND EXISTS (SELECT 1 FROM tenants WHERE id = OLD.tenant_id)
                       AND (SELECT COUNT(*) FROM price_lists WHERE tenant_id = OLD.tenant_id AND is_default AND status = 'ACTIVE') <> 1 THEN
                        RAISE EXCEPTION 'A tenant requires exactly one active default price list' USING ERRCODE = '23514';
                    END IF;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER price_lists_default_check
            AFTER INSERT OR UPDATE OR DELETE ON price_lists
            DEFERRABLE INITIALLY IMMEDIATE
            FOR EACH ROW EXECUTE FUNCTION enforce_tenant_default_price_list()
        SQL);
        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER tenants_default_price_list_check
            AFTER INSERT ON tenants
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION enforce_tenant_default_price_list()
        SQL);
    }
};
