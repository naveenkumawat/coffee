<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_lines', function (Blueprint $table): void {
            $table->boolean('show_to_customer')->default(false)->after('sort_order');
            $table->string('customer_label', 120)->nullable()->after('show_to_customer');
        });
    }

    public function down(): void
    {
        Schema::table('recipe_lines', function (Blueprint $table): void {
            $table->dropColumn(['show_to_customer', 'customer_label']);
        });
    }
};
