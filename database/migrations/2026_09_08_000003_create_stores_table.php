<?php

use App\Modules\Stores\Domain\Enums\StoreStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->enum('status', array_column(StoreStatus::cases(), 'value'))
                ->default(StoreStatus::Active->value);
            $table->char('currency', 3)->default('PEN');
            $table->string('timezone', 64)->default('America/Lima');
            $table->string('locale', 16)->default('es_PE');
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
