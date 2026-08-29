<?php

namespace Database\Factories;

use App\Enums\ProductServingUnit;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Small', 'Regular', 'Large']),
            'serving_size_value' => fake()->randomElement(['250.000', '300.000', '500.000']),
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => fake()->randomElement(['3.95', '4.75', '5.50']),
            'sort_order' => fake()->numberBetween(1, 5),
            'is_active' => true,
            'is_available' => true,
        ];
    }
}
