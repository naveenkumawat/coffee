<?php

namespace Database\Factories;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFulfilmentScope;
use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Offer',
            'code' => null,
            'description' => fake()->optional()->sentence(),
            'type' => PromotionType::Automatic,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => fake()->randomElement([5, 10, 15, 20]),
            'starts_at' => null,
            'ends_at' => null,
            'minimum_subtotal' => null,
            'maximum_discount_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => null,
            'priority' => 0,
            'is_active' => true,
            'stackable' => false,
            'applies_to_all_products' => true,
            'applies_to_all_customers' => true,
            'first_order_only' => false,
            'fulfilment_scope' => PromotionFulfilmentScope::Any,
            'weekdays' => null,
            'daily_starts_at' => null,
            'daily_ends_at' => null,
            'customer_message' => fake()->optional()->sentence(6),
            'internal_note' => null,
        ];
    }

    public function automatic(): static
    {
        return $this->state(fn (): array => [
            'type' => PromotionType::Automatic,
            'code' => null,
        ]);
    }

    public function coupon(?string $code = null): static
    {
        return $this->state(fn (): array => [
            'type' => PromotionType::Coupon,
            'code' => strtoupper($code ?? fake()->unique()->bothify('SAVE##')),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);
    }

    public function dineIn(): static
    {
        return $this->state(fn (): array => [
            'fulfilment_scope' => PromotionFulfilmentScope::DineIn,
        ]);
    }

    public function percentage(float $value = 10): static
    {
        return $this->state(fn (): array => [
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => $value,
        ]);
    }

    public function fixed(float $value = 100): static
    {
        return $this->state(fn (): array => [
            'discount_type' => PromotionDiscountType::Fixed,
            'discount_value' => $value,
        ]);
    }
}
