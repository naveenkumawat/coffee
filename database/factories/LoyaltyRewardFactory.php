<?php

namespace Database\Factories;

use App\Enums\LoyaltyRewardStatus;
use App\Enums\LoyaltyRewardType;
use App\Models\LoyaltyReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyReward>
 */
class LoyaltyRewardFactory extends Factory
{
    protected $model = LoyaltyReward::class;

    public function definition(): array
    {
        return [
            'name' => 'Loyalty reward '.$this->faker->unique()->numerify('###'),
            'status' => LoyaltyRewardStatus::Active,
            'reward_type' => LoyaltyRewardType::FixedOrderDiscount,
            'points_cost' => 100,
            'config' => [
                'discount_amount' => '50.00',
            ],
            'minimum_spend' => null,
            'starts_at' => null,
            'ends_at' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => null,
            'usage_limit_per_customer_period_days' => null,
            'priority' => 0,
            'customer_description' => 'Redeem points for a discount.',
            'internal_note' => null,
        ];
    }

    public function percentage(float $percent = 10, ?float $maxDiscount = null): static
    {
        return $this->state(fn (): array => [
            'reward_type' => LoyaltyRewardType::PercentageOrderDiscount,
            'config' => array_filter([
                'percent' => $percent,
                'maximum_discount_amount' => $maxDiscount !== null
                    ? number_format($maxDiscount, 2, '.', '')
                    : null,
            ], static fn ($value): bool => $value !== null),
        ]);
    }

    public function fixed(string $amount = '50.00'): static
    {
        return $this->state(fn (): array => [
            'reward_type' => LoyaltyRewardType::FixedOrderDiscount,
            'config' => [
                'discount_amount' => number_format((float) $amount, 2, '.', ''),
            ],
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (): array => [
            'status' => LoyaltyRewardStatus::Paused,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => LoyaltyRewardStatus::Archived,
        ]);
    }
}
