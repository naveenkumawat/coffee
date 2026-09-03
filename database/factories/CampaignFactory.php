<?php

namespace Database\Factories;

use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'internal_label' => fake()->optional()->slug(),
            'status' => CampaignStatus::Draft,
            'surface' => CampaignSurface::Popup,
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(12),
            'image_path' => null,
            'cta_label' => 'Shop now',
            'cta_type' => CampaignCtaType::Close,
            'cta_product_id' => null,
            'cta_category_id' => null,
            'cta_promotion_id' => null,
            'cta_internal_path' => null,
            'priority' => fake()->numberBetween(0, 50),
            'starts_at' => null,
            'ends_at' => null,
            'frequency_policy' => CampaignFrequencyPolicy::OncePerSession,
            'cooldown_hours' => null,
            'max_impressions' => null,
            'placement_rules' => [
                'placements' => ['global'],
                'category_ids' => [],
                'product_ids' => [],
                'product_tag_ids' => [],
            ],
            'targeting_rules' => [
                'all' => [
                    ['type' => 'identity', 'op' => 'eq', 'value' => 'everyone'],
                ],
                'any' => [],
                'exclude' => [],
            ],
            'trigger_rules' => [
                'type' => CampaignTriggerType::Immediate->value,
                'delay_ms' => null,
                'scroll_percent' => null,
                'product_view_count' => null,
            ],
            'attribution_key' => 'cmp_'.Str::lower(Str::random(16)),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function popup(): static
    {
        return $this->state(fn (): array => [
            'surface' => CampaignSurface::Popup,
        ]);
    }
}
