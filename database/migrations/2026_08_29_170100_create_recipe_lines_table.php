<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->restrictOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->string('measurement_unit', 20);
            $table->decimal('base_quantity', 10, 3);
            $table->string('base_measurement_unit', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['recipe_id', 'ingredient_id'], 'recipe_ingredient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_lines');
    }
};
