<?php

namespace Database\Factories;

use App\Models\AddOn;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AddOn>
 */
class AddOnFactory extends Factory
{
    protected $model = AddOn::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'description' => fake()->optional()->sentence(),
            'default_price' => fake()->randomFloat(2, 0.5, 5),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
