<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            $table->json('attribution')->nullable()->after('quantity');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('attribution')->nullable()->after('line_subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('attribution');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropColumn('attribution');
        });
    }
};
