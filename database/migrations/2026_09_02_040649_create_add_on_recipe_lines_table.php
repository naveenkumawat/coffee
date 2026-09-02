<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('add_on_recipe_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('add_on_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('measurement_unit', 20);
            $table->decimal('base_quantity', 12, 3);
            $table->string('base_measurement_unit', 20);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['add_on_id', 'ingredient_id'], 'add_on_recipe_ingredient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('add_on_recipe_lines');
    }
};
