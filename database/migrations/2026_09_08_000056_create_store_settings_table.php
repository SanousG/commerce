<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('store_id')->unique();
            $table->string('display_name', 120)->nullable();
            $table->string('short_description', 280)->nullable();
            $table->string('contact_email', 254)->nullable();
            $table->string('contact_phone', 16)->nullable();
            $table->string('whatsapp_phone', 16)->nullable();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->char('country_code', 2)->default('PE');
            $table->string('postal_code', 32)->nullable();
            $table->jsonb('social_links')->default('{}');
            $table->uuid('logo_media_asset_id')->nullable();
            $table->uuid('favicon_media_asset_id')->nullable();
            $table->char('primary_color', 7)->default('#17211B');
            $table->char('secondary_color', 7)->nullable();
            $table->timestampsTz();

            $table->unique(['id', 'tenant_id'], 'store_settings_id_tenant_unique');
            $table->index(['tenant_id', 'store_id']);
            $table->foreign(['store_id', 'tenant_id'])->references(['id', 'tenant_id'])
                ->on('stores')->cascadeOnDelete();
            $table->foreign(['logo_media_asset_id', 'tenant_id'])->references(['id', 'tenant_id'])
                ->on('media_assets')->restrictOnDelete();
            $table->foreign(['favicon_media_asset_id', 'tenant_id'])->references(['id', 'tenant_id'])
                ->on('media_assets')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE store_settings ADD CONSTRAINT store_settings_country_code_check CHECK (country_code ~ '^[A-Z]{2}$')");
        DB::statement("ALTER TABLE store_settings ADD CONSTRAINT store_settings_primary_color_check CHECK (primary_color ~ '^#[0-9A-F]{6}$')");
        DB::statement("ALTER TABLE store_settings ADD CONSTRAINT store_settings_secondary_color_check CHECK (secondary_color IS NULL OR secondary_color ~ '^#[0-9A-F]{6}$')");
        DB::statement("ALTER TABLE store_settings ADD CONSTRAINT store_settings_contact_phone_check CHECK (contact_phone IS NULL OR contact_phone ~ '^\\+[1-9][0-9]{6,14}$')");
        DB::statement("ALTER TABLE store_settings ADD CONSTRAINT store_settings_whatsapp_phone_check CHECK (whatsapp_phone IS NULL OR whatsapp_phone ~ '^\\+[1-9][0-9]{6,14}$')");
        DB::statement("ALTER TABLE store_settings ADD CONSTRAINT store_settings_social_links_object_check CHECK (jsonb_typeof(social_links) = 'object')");

        $now = now();
        DB::table('stores')->orderBy('id')->chunk(500, function ($stores) use ($now): void {
            DB::table('store_settings')->insert($stores->map(fn ($store): array => [
                'id' => (string) Str::uuid7(),
                'tenant_id' => $store->tenant_id,
                'store_id' => $store->id,
                'country_code' => 'PE',
                'social_links' => '{}',
                'primary_color' => '#17211B',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
