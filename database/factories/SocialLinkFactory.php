<?php

namespace Database\Factories;

use App\Enums\SocialIconKey;
use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialLink>
 */
class SocialLinkFactory extends Factory
{
    protected $model = SocialLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $icon = fake()->randomElement(SocialIconKey::cases());

        return [
            'platform_key' => $icon->value.'_'.fake()->unique()->numerify('###'),
            'label' => SocialIconKey::options()[$icon->value],
            'url' => fake()->optional()->url(),
            'icon_key' => $icon->value,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function withoutUrl(): static
    {
        return $this->state(fn (): array => [
            'url' => null,
        ]);
    }
}
