<?php

namespace Database\Factories;

use App\Enums\CustomerRewardStatus;
use App\Enums\CustomerRewardType;
use App\Enums\PromotionDiscountType;
use App\Models\CustomerReferral;
use App\Models\CustomerReward;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReward>
 */
class CustomerRewardFactory extends Factory
{
    protected $model = CustomerReward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'source_type' => 'referral',
            'source_referral_id' => null,
            'reward_type' => CustomerRewardType::FreeDrink,
            'status' => CustomerRewardStatus::Available,
            'earned_at' => now(),
            'expires_at' => now()->addDays(30),
            'redeemed_order_id' => null,
            'redeemed_at' => null,
            'product_id' => null,
            'variant_id' => null,
            'product_name_snapshot' => null,
            'variant_name_snapshot' => null,
            'quantity' => 1,
            'coupon_code' => null,
            'discount_type' => null,
            'discount_value' => null,
            'maximum_discount_amount' => null,
            'minimum_subtotal' => null,
        ];
    }

    public function freeDrink(?Product $product = null, ?int $variantId = null): static
    {
        return $this->state(function () use ($product, $variantId): array {
            $product ??= Product::factory()->create();

            return [
                'reward_type' => CustomerRewardType::FreeDrink,
                'product_id' => $product->getKey(),
                'variant_id' => $variantId,
                'product_name_snapshot' => $product->name,
                'quantity' => 1,
            ];
        });
    }

    public function coupon(string $code = 'REF-TEST01', string $value = '50.00'): static
    {
        return $this->state(fn (): array => [
            'reward_type' => CustomerRewardType::Coupon,
            'coupon_code' => $code,
            'discount_type' => PromotionDiscountType::Fixed,
            'discount_value' => $value,
            'product_id' => null,
            'variant_id' => null,
            'product_name_snapshot' => null,
            'quantity' => null,
        ]);
    }

    public function forReferral(CustomerReferral $referral): static
    {
        return $this->state(fn (): array => [
            'user_id' => $referral->referrer_user_id,
            'source_referral_id' => $referral->getKey(),
            'source_type' => 'referral',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
            'status' => CustomerRewardStatus::Available,
        ]);
    }

    public function redeemed(): static
    {
        return $this->state(fn (): array => [
            'status' => CustomerRewardStatus::Redeemed,
            'redeemed_at' => now(),
        ]);
    }
}
