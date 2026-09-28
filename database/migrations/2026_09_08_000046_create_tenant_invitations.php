<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email', 255);
            $table->foreignUuid('role_id')->constrained('roles')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->enum('status', ['PENDING', 'ACCEPTED', 'EXPIRED', 'REVOKED'])->default('PENDING');
            $table->foreignId('invited_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'email']);
            $table->index('expires_at');
        });

        DB::statement("CREATE UNIQUE INDEX tenant_invitations_pending_email_unique ON tenant_invitations (tenant_id, email) WHERE status = 'PENDING'");
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_invitations
            ADD CONSTRAINT tenant_invitations_normalized_email_check
            CHECK (email = lower(btrim(email)))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
