<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->string('product_name', 180);
            $table->string('variant_name', 180);
            $table->string('customer_ingredient_summary', 255)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_subtotal', 12, 2);
            $table->timestamps();

            $table->index(['order_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
