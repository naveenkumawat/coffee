<?php

namespace Database\Factories;

use App\Enums\HomeSectionPlacement;
use App\Enums\HomeSectionSourceType;
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
            'placement' => HomeSectionPlacement::Home,
            'source_type' => HomeSectionSourceType::Curated,
            'priority' => 0,
            'targeting_rules' => ['all' => [], 'any' => [], 'exclude' => []],
            'dedupe_products' => true,
            'fallback_to_curated' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function forMenu(): static
    {
        return $this->state(fn (): array => [
            'placement' => HomeSectionPlacement::Menu,
        ]);
    }
}
