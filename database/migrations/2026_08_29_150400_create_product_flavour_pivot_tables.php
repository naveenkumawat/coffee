<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_flavour_product')) {
            Schema::create('product_flavour_product', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_flavour_id')->constrained('product_flavours')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['product_id', 'product_flavour_id'], 'pfp_product_flavour_unique');
            });
        }

        if (! Schema::hasTable('product_category_product_flavour')) {
            Schema::create('product_category_product_flavour', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
                $table->foreignId('product_flavour_id')->constrained('product_flavours')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['product_category_id', 'product_flavour_id'], 'pcpf_category_flavour_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_product_flavour');
        Schema::dropIfExists('product_flavour_product');
    }
};
