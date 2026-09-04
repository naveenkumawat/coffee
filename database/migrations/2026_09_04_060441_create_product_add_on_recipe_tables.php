<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_add_on') && ! Schema::hasColumn('product_add_on', 'is_active')) {
            Schema::table('product_add_on', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->after('max_quantity');
            });
        }

        if (Schema::hasTable('add_ons') && ! Schema::hasColumn('add_ons', 'image_path')) {
            Schema::table('add_ons', function (Blueprint $table): void {
                $table->string('image_path')->nullable()->after('description');
            });
        }

        if (! Schema::hasTable('product_add_on_recipe_lines')) {
            Schema::create('product_add_on_recipe_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_add_on_id')->constrained('product_add_on')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
                $table->decimal('quantity', 12, 3);
                $table->string('measurement_unit', 20);
                $table->decimal('base_quantity', 12, 3);
                $table->string('base_measurement_unit', 20);
                $table->unsignedInteger('sort_order')->default(10);
                $table->timestamps();

                $table->unique(['product_add_on_id', 'ingredient_id'], 'product_add_on_recipe_unique');
                $table->index(['product_add_on_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('product_variant_add_on_recipe_lines')) {
            Schema::create('product_variant_add_on_recipe_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_add_on_id')->constrained('product_add_on')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
                $table->decimal('quantity', 12, 3);
                $table->string('measurement_unit', 20);
                $table->decimal('base_quantity', 12, 3);
                $table->string('base_measurement_unit', 20);
                $table->unsignedInteger('sort_order')->default(10);
                $table->timestamps();

                $table->unique(
                    ['product_variant_id', 'product_add_on_id', 'ingredient_id'],
                    'variant_add_on_recipe_unique',
                );
                $table->index(['product_variant_id', 'product_add_on_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_add_on_recipe_lines');
        Schema::dropIfExists('product_add_on_recipe_lines');

        if (Schema::hasTable('add_ons') && Schema::hasColumn('add_ons', 'image_path')) {
            Schema::table('add_ons', function (Blueprint $table): void {
                $table->dropColumn('image_path');
            });
        }

        if (Schema::hasTable('product_add_on') && Schema::hasColumn('product_add_on', 'is_active')) {
            Schema::table('product_add_on', function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};
