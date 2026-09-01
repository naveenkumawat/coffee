<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('product_type', 20)->default('beverage')->after('product_category_id');
            $table->string('preparation_station', 20)->default('bar')->after('product_type');

            $table->index(['product_type', 'is_active']);
            $table->index(['preparation_station', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['product_type', 'is_active']);
            $table->dropIndex(['preparation_station', 'is_active']);
            $table->dropColumn(['product_type', 'preparation_station']);
        });
    }
};
