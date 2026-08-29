<?php

namespace Database\Factories;

use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Models\IngredientBrand;
use App\Models\IngredientCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $unit = fake()->randomElement([
            IngredientUnit::Gram,
            IngredientUnit::Milliliter,
            IngredientUnit::Piece,
            IngredientUnit::Bottle,
            IngredientUnit::Pack,
        ]);

        return [
            'ingredient_category_id' => IngredientCategory::factory(),
            'ingredient_brand_id' => IngredientBrand::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'measurement_unit' => $unit,
            'base_measurement_unit' => $unit->baseUnit(),
            'purchase_quantity' => '100.000',
            'purchase_quantity_base' => '100.000',
            'purchase_cost' => '250.00',
            'cost_per_unit' => '2.5000',
            'current_stock' => '80.000',
            'minimum_stock' => '20.000',
            'reorder_level' => '30.000',
            'supplier_name' => fake()->company(),
            'supplier_email' => fake()->companyEmail(),
            'supplier_phone' => fake()->phoneNumber(),
            'supplier_notes' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
