<?php

use App\Modules\Tenancy\Domain\Enums\TenantMemberRole;
use App\Modules\Tenancy\Domain\Enums\TenantMemberStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', array_column(TenantMemberRole::cases(), 'value'));
            $table->enum('status', array_column(TenantMemberStatus::cases(), 'value'))
                ->default(TenantMemberStatus::Active->value);
            $table->timestampTz('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_members');
    }
};
