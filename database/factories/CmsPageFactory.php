<?php

namespace Database\Factories;

use App\Enums\CmsPageKey;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CmsPage>
 */
class CmsPageFactory extends Factory
{
    protected $model = CmsPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'seo_title' => null,
            'meta_description' => null,
            'body' => fake()->paragraphs(2, true),
            'is_published' => true,
            'is_system' => false,
        ];
    }

    public function system(CmsPageKey $key): static
    {
        return $this->state(fn (): array => [
            'key' => $key->value,
            'title' => $key->title(),
            'is_system' => true,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => [
            'is_published' => false,
            'body' => null,
        ]);
    }
}
