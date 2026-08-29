<?php

namespace Database\Factories;

use App\Models\ProductFlavour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductFlavour>
 */
class ProductFlavourFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'image_path' => null,
            'is_active' => true,
        ];
    }
}
