<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingredient_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('brand', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('measurement_unit', 20);
            $table->string('base_measurement_unit', 20);
            $table->decimal('purchase_quantity', 12, 3);
            $table->decimal('purchase_quantity_base', 12, 3);
            $table->decimal('purchase_cost', 12, 2);
            $table->decimal('cost_per_unit', 12, 4);
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('minimum_stock', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->string('supplier_name', 160)->nullable();
            $table->string('supplier_email', 160)->nullable();
            $table->string('supplier_phone', 40)->nullable();
            $table->text('supplier_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['ingredient_category_id', 'is_active']);
            $table->index('measurement_unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
