<?php

use App\Modules\Stores\Domain\Enums\StorefrontPublicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id')->unique();
            $table->string('public_handle', 63)->unique();
            $table->string('status', 20)->default(StorefrontPublicationStatus::Unpublished->value);
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE storefront_publications ADD CONSTRAINT storefront_publications_handle_check CHECK (public_handle = lower(public_handle) AND public_handle ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE storefront_publications ADD CONSTRAINT storefront_publications_status_check CHECK (status IN ('UNPUBLISHED', 'PUBLISHED'))");
        DB::statement("ALTER TABLE storefront_publications ADD CONSTRAINT storefront_publications_published_at_check CHECK ((status = 'UNPUBLISHED' AND published_at IS NULL) OR (status = 'PUBLISHED' AND published_at IS NOT NULL))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_storefront_publication_tenant() RETURNS trigger AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM stores
                    WHERE stores.id = NEW.store_id
                      AND stores.tenant_id = NEW.tenant_id
                ) THEN
                    RAISE EXCEPTION 'Storefront publication Tenant and Store must match'
                        USING ERRCODE = '23503';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER storefront_publications_tenant_store_check
            BEFORE INSERT OR UPDATE OF tenant_id, store_id ON storefront_publications
            FOR EACH ROW EXECUTE FUNCTION enforce_storefront_publication_tenant();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_publications');
        DB::statement('DROP FUNCTION IF EXISTS enforce_storefront_publication_tenant()');
    }
};
