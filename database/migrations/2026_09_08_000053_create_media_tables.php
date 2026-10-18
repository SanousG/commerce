<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('disk', 80);
            $table->string('storage_key', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title', 160)->nullable();
            $table->text('alt_text')->nullable();
            $table->char('checksum', 64);
            $table->string('status', 20);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id', 'tenant_id']);
            $table->unique(['disk', 'storage_key']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
        });
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_type_check CHECK (type IN ('IMAGE', 'DOCUMENT'))");
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_status_check CHECK (status IN ('READY', 'DELETING'))");
        DB::statement('ALTER TABLE media_assets ADD CONSTRAINT media_assets_dimensions_check CHECK ((width IS NULL AND height IS NULL) OR (width > 0 AND height > 0))');

        Schema::create('media_derivatives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('media_asset_id');
            $table->string('variant', 30);
            $table->string('disk', 80);
            $table->string('storage_key', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64);
            $table->timestamps();

            $table->unique(['media_asset_id', 'variant']);
            $table->unique(['disk', 'storage_key']);
            $table->foreign(['media_asset_id', 'tenant_id'])->references(['id', 'tenant_id'])
                ->on('media_assets')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE media_derivatives ADD CONSTRAINT media_derivatives_variant_check CHECK (variant IN ('THUMBNAIL', 'MEDIUM'))");
        DB::statement('ALTER TABLE media_derivatives ADD CONSTRAINT media_derivatives_dimensions_check CHECK (width > 0 AND height > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('media_derivatives');
        Schema::dropIfExists('media_assets');
    }
};
