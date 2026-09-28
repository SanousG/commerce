<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('tax_category_id')->nullable();
            $table->index(['tenant_id', 'tax_category_id']);
            $table->foreign(['tax_category_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('tax_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tax_category_id', 'tenant_id']);
            $table->dropIndex(['tenant_id', 'tax_category_id']);
            $table->dropColumn('tax_category_id');
        });
    }
};
