<?php

namespace Database\Factories;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory()->for(
                Product::factory()->for(ProductCategory::factory(), 'category'),
                'product',
            ),
            'version' => 1,
            'preparation_notes' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Attach one stocked ingredient line so accept / dining-round consumption can succeed in tests.
     */
    public function withDefaultLine(): static
    {
        return $this->afterCreating(function (Recipe $recipe): void {
            if ($recipe->lines()->exists()) {
                return;
            }

            $ingredient = Ingredient::factory()->create([
                'measurement_unit' => IngredientUnit::Gram,
                'base_measurement_unit' => IngredientUnit::Gram,
                'current_stock' => '100000.000',
                'minimum_stock' => '10.000',
                'reorder_level' => '20.000',
            ]);

            RecipeLine::query()->create([
                'recipe_id' => $recipe->getKey(),
                'ingredient_id' => $ingredient->getKey(),
                'quantity' => '1.000',
                'measurement_unit' => IngredientUnit::Gram->value,
                'base_quantity' => '1.000',
                'base_measurement_unit' => IngredientUnit::Gram->value,
                'sort_order' => 0,
            ]);
        });
    }
}
