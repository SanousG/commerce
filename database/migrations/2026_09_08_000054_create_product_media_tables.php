<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['id', 'tenant_id']);
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('product_id');
            $table->uuid('media_asset_id');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->text('alt_text')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['product_id', 'media_asset_id']);
            $table->index(['product_id', 'position']);
            $table->foreign(['product_id', 'tenant_id'])->references(['id', 'tenant_id'])
                ->on('products')->cascadeOnDelete();
            $table->foreign(['media_asset_id', 'tenant_id'])->references(['id', 'tenant_id'])
                ->on('media_assets')->restrictOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX product_media_one_primary_unique ON product_media (product_id) WHERE is_primary');
        DB::statement('ALTER TABLE product_media ADD CONSTRAINT product_media_position_check CHECK (position >= 0)');

        Schema::create('product_variant_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('product_variant_id');
            $table->uuid('media_asset_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['product_variant_id', 'media_asset_id']);
            $table->index(['product_variant_id', 'position']);
            $table->foreign(['product_variant_id', 'tenant_id'])->references(['id', 'tenant_id'])
                ->on('product_variants')->cascadeOnDelete();
            $table->foreign(['media_asset_id', 'tenant_id'])->references(['id', 'tenant_id'])
                ->on('media_assets')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE product_variant_media ADD CONSTRAINT product_variant_media_position_check CHECK (position >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_media');
        Schema::dropIfExists('product_media');
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['id', 'tenant_id']);
        });
    }
};
