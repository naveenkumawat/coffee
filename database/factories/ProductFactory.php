<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'product_category_id' => ProductCategory::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'sku' => strtoupper(Str::random(8)),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'customer_ingredient_summary' => fake()->words(3, true),
            'image_path' => null,
            'preparation_time_minutes' => fake()->numberBetween(3, 8),
            'sort_order' => fake()->numberBetween(1, 25),
            'is_active' => true,
            'is_available' => true,
            'is_featured' => fake()->boolean(35),
        ];
    }
}
