<?php

namespace App\Enums;

enum LoyaltyRewardType: string
{
    case FixedOrderDiscount = 'fixed_order_discount';
    case PercentageOrderDiscount = 'percentage_order_discount';
    case FreeBaseProduct = 'free_base_product';
    case FreeAddOn = 'free_add_on';
    case SpecificProductReward = 'specific_product_reward';
    case CategoryProductReward = 'category_product_reward';

    public function label(): string
    {
        return match ($this) {
            self::FixedOrderDiscount => 'Fixed order discount',
            self::PercentageOrderDiscount => 'Percentage order discount',
            self::FreeBaseProduct => 'Free base product',
            self::FreeAddOn => 'Free add-on',
            self::SpecificProductReward => 'Specific product reward',
            self::CategoryProductReward => 'Category product reward',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
