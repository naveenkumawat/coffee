<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
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
}
