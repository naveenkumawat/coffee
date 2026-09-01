<?php

namespace Database\Seeders;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFulfilmentScope;
use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local/testing demo offers only. Never seed in production.
 */
class DemoPromotionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoPromotionSeeder refused: demo offers must never be seeded outside local/testing (APP_ENV='.app()->environment().').',
            );
        }

        $this->upsert([
            'name' => 'Dine-in 10%',
            'code' => null,
            'description' => 'Automatic 10% off for dine-in orders.',
            'type' => PromotionType::Automatic,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 10,
            'starts_at' => null,
            'ends_at' => null,
            'minimum_subtotal' => null,
            'maximum_discount_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => null,
            'priority' => 10,
            'is_active' => true,
            'stackable' => false,
            'applies_to_all_products' => true,
            'applies_to_all_customers' => true,
            'first_order_only' => false,
            'fulfilment_scope' => PromotionFulfilmentScope::DineIn,
            'weekdays' => null,
            'daily_starts_at' => null,
            'daily_ends_at' => null,
            'customer_message' => '10% dine-in discount applied.',
            'internal_note' => 'Demo automatic dine-in offer.',
        ], matchOn: ['name' => 'Dine-in 10%']);

        $this->upsert([
            'name' => 'Festival Coffee Offer',
            'code' => null,
            'description' => 'Seasonal automatic 5% off coffee orders.',
            'type' => PromotionType::Automatic,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 5,
            'starts_at' => now()->subDay()->startOfDay(),
            'ends_at' => now()->addDays(7)->endOfDay(),
            'minimum_subtotal' => null,
            'maximum_discount_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => null,
            'priority' => 20,
            'is_active' => true,
            'stackable' => true,
            'applies_to_all_products' => true,
            'applies_to_all_customers' => true,
            'first_order_only' => false,
            'fulfilment_scope' => PromotionFulfilmentScope::Any,
            'weekdays' => null,
            'daily_starts_at' => null,
            'daily_ends_at' => null,
            'customer_message' => 'Festival 5% off applied.',
            'internal_note' => 'Demo festival window: yesterday through +7 days.',
        ], matchOn: ['name' => 'Festival Coffee Offer']);

        $this->upsert([
            'name' => 'Bulk ₹100 Off',
            'code' => 'BULK500',
            'description' => '₹100 off when cart subtotal is at least ₹500.',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Fixed,
            'discount_value' => 100,
            'starts_at' => null,
            'ends_at' => null,
            'minimum_subtotal' => 500,
            'maximum_discount_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => 5,
            'priority' => 50,
            'is_active' => true,
            'stackable' => false,
            'applies_to_all_products' => true,
            'applies_to_all_customers' => true,
            'first_order_only' => false,
            'fulfilment_scope' => PromotionFulfilmentScope::Any,
            'weekdays' => null,
            'daily_starts_at' => null,
            'daily_ends_at' => null,
            'customer_message' => 'BULK500: ₹100 off applied.',
            'internal_note' => 'Demo coupon BULK500.',
        ], matchOn: ['code' => 'BULK500']);

        $this->upsert([
            'name' => 'Diwali 15%',
            'code' => 'DIWALI15',
            'description' => '15% off with a maximum discount cap.',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 15,
            'starts_at' => now()->subDay()->startOfDay(),
            'ends_at' => now()->addDays(14)->endOfDay(),
            'minimum_subtotal' => null,
            'maximum_discount_amount' => 150,
            'usage_limit' => 1000,
            'usage_limit_per_customer' => 1,
            'priority' => 40,
            'is_active' => true,
            'stackable' => false,
            'applies_to_all_products' => true,
            'applies_to_all_customers' => true,
            'first_order_only' => false,
            'fulfilment_scope' => PromotionFulfilmentScope::Any,
            'weekdays' => null,
            'daily_starts_at' => null,
            'daily_ends_at' => null,
            'customer_message' => 'DIWALI15: up to ₹150 off.',
            'internal_note' => 'Demo coupon DIWALI15 with max cap.',
        ], matchOn: ['code' => 'DIWALI15']);

        $this->upsert([
            'name' => 'Inactive Welcome Offer',
            'code' => 'WELCOME20',
            'description' => 'Inactive coupon kept for admin UI demos.',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 20,
            'starts_at' => null,
            'ends_at' => null,
            'minimum_subtotal' => null,
            'maximum_discount_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => 1,
            'priority' => 0,
            'is_active' => false,
            'stackable' => false,
            'applies_to_all_products' => true,
            'applies_to_all_customers' => true,
            'first_order_only' => true,
            'fulfilment_scope' => PromotionFulfilmentScope::Any,
            'weekdays' => null,
            'daily_starts_at' => null,
            'daily_ends_at' => null,
            'customer_message' => 'Welcome 20% off.',
            'internal_note' => 'Demo inactive coupon.',
        ], matchOn: ['code' => 'WELCOME20']);

        $this->upsert([
            'name' => 'Expired Summer Special',
            'code' => null,
            'description' => 'Expired automatic offer for admin UI demos.',
            'type' => PromotionType::Automatic,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 8,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
            'minimum_subtotal' => null,
            'maximum_discount_amount' => null,
            'usage_limit' => null,
            'usage_limit_per_customer' => null,
            'priority' => 5,
            'is_active' => true,
            'stackable' => false,
            'applies_to_all_products' => true,
            'applies_to_all_customers' => true,
            'first_order_only' => false,
            'fulfilment_scope' => PromotionFulfilmentScope::Any,
            'weekdays' => null,
            'daily_starts_at' => null,
            'daily_ends_at' => null,
            'customer_message' => 'Summer special 8% off.',
            'internal_note' => 'Demo expired automatic offer.',
        ], matchOn: ['name' => 'Expired Summer Special']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $matchOn
     */
    protected function upsert(array $attributes, array $matchOn): void
    {
        $promotion = Promotion::query()->withTrashed()->firstOrNew($matchOn);
        $promotion->fill($attributes);
        $promotion->deleted_at = null;
        $promotion->save();
    }
}
