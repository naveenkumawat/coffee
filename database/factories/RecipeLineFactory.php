<?php

namespace Database\Factories;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipeLine>
 */
class RecipeLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'ingredient_id' => Ingredient::factory(),
            'quantity' => '10.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_quantity' => '10.000',
            'base_measurement_unit' => IngredientUnit::Gram,
            'sort_order' => 1,
            'show_to_customer' => false,
            'customer_label' => null,
        ];
    }
}
