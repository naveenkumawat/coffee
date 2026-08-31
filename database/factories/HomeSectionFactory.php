<?php

namespace Database\Factories;

use App\Models\HomeSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HomeSection>
 */
class HomeSectionFactory extends Factory
{
    protected $model = HomeSection::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'name' => $title,
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'subtitle' => fake()->optional()->sentence(4),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
            'max_items' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
