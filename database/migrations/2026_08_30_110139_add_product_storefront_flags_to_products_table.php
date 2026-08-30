<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_new')->default(false)->after('is_featured');
            $table->boolean('is_bestseller')->default(false)->after('is_new');
            $table->boolean('is_vegetarian')->default(false)->after('is_bestseller');
            $table->boolean('is_customizable')->default(false)->after('is_vegetarian');

            $table->index(['is_available', 'is_new']);
            $table->index(['is_available', 'is_bestseller']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_available', 'is_new']);
            $table->dropIndex(['is_available', 'is_bestseller']);
            $table->dropColumn([
                'is_new',
                'is_bestseller',
                'is_vegetarian',
                'is_customizable',
            ]);
        });
    }
};
