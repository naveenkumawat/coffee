<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredient_categories', function (Blueprint $table): void {
            $table->dropColumn('sort_order');
        });

        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropColumn('brand');
        });
    }

    public function down(): void
    {
        Schema::table('ingredient_categories', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(10)->after('description');
        });

        Schema::table('ingredients', function (Blueprint $table): void {
            $table->string('brand', 160)->nullable()->after('slug');
        });
    }
};
