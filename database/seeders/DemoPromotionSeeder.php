<?php

namespace Database\Seeders;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFulfilmentScope;
use App\Enums\PromotionType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local/testing demo offers covering supported promotion variations.
 * Never seed in production.
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

        $hotCoffee = ProductCategory::query()->where('slug', 'hot-coffee')->first();
        $espresso = Product::query()->where('name', 'Espresso')->first();
        $cappuccino = Product::query()->where('name', 'Cappuccino')->first();

        // Legacy demo offers retained for existing admin/QA flows.
        $this->seedLegacyOffers();

        $definitions = [
            [
                'match' => ['name' => '[Demo] 10% Off Above ₹500'],
                'attrs' => $this->base([
                    'name' => '[Demo] 10% Off Above ₹500',
                    'description' => 'Example: Percentage discount above minimum order amount.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 10,
                    'minimum_subtotal' => 500,
                    'priority' => 30,
                    'customer_message' => '10% off orders over ₹500.',
                    'internal_note' => 'Demo: percentage + minimum subtotal.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] ₹50 Off Takeaway'],
                'attrs' => $this->base([
                    'name' => '[Demo] ₹50 Off Takeaway',
                    'description' => 'Example: Fixed amount discount limited to takeaway.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Fixed,
                    'discount_value' => 50,
                    'fulfilment_scope' => PromotionFulfilmentScope::Takeaway,
                    'priority' => 35,
                    'customer_message' => '₹50 off takeaway applied.',
                    'internal_note' => 'Demo: fixed + takeaway scope.',
                ]),
            ],
            [
                'match' => ['code' => 'DEMODEL50'],
                'attrs' => $this->base([
                    'name' => '[Demo] ₹50 Off Delivery',
                    'code' => 'DEMODEL50',
                    'description' => 'Example: Coupon fixed discount for delivery only.',
                    'type' => PromotionType::Coupon,
                    'discount_type' => PromotionDiscountType::Fixed,
                    'discount_value' => 50,
                    'fulfilment_scope' => PromotionFulfilmentScope::Delivery,
                    'usage_limit_per_customer' => 3,
                    'priority' => 36,
                    'customer_message' => 'DEMODEL50: ₹50 off delivery.',
                    'internal_note' => 'Demo: coupon + delivery scope.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] DINING Only Promotion'],
                'attrs' => $this->base([
                    'name' => '[Demo] DINING Only Promotion',
                    'description' => 'Example: Dining fulfilment scope (table service).',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 8,
                    'fulfilment_scope' => PromotionFulfilmentScope::Dining,
                    'priority' => 12,
                    'customer_message' => 'Dining 8% discount applied.',
                    'internal_note' => 'Demo: dining fulfilment scope.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Dine-In Scope Promo'],
                'attrs' => $this->base([
                    'name' => '[Demo] Dine-In Scope Promo',
                    'description' => 'Example: dine_in fulfilment scope alias.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Fixed,
                    'discount_value' => 25,
                    'fulfilment_scope' => PromotionFulfilmentScope::DineIn,
                    'priority' => 13,
                    'customer_message' => '₹25 dine-in discount.',
                    'internal_note' => 'Demo: dine_in fulfilment scope.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Weekend Coffee Offer'],
                'attrs' => $this->base([
                    'name' => '[Demo] Weekend Coffee Offer',
                    'description' => 'Example: Scheduled weekend weekdays (Sat/Sun).',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 12,
                    'weekdays' => [0, 6],
                    'priority' => 25,
                    'customer_message' => 'Weekend 12% off.',
                    'internal_note' => 'Demo: weekday schedule (0=Sun, 6=Sat).',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Happy Hour 4–7pm'],
                'attrs' => $this->base([
                    'name' => '[Demo] Happy Hour 4–7pm',
                    'description' => 'Example: Daily time window promotion.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 15,
                    'daily_starts_at' => '16:00',
                    'daily_ends_at' => '19:00',
                    'priority' => 28,
                    'customer_message' => 'Happy hour 15% off.',
                    'internal_note' => 'Demo: daily_starts_at / daily_ends_at.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] First Order Offer'],
                'attrs' => $this->base([
                    'name' => '[Demo] First Order Offer',
                    'description' => 'Example: First order only automatic discount.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Fixed,
                    'discount_value' => 40,
                    'first_order_only' => true,
                    'usage_limit_per_customer' => 1,
                    'priority' => 45,
                    'customer_message' => 'First order ₹40 off.',
                    'internal_note' => 'Demo: first_order_only.',
                ]),
            ],
            [
                'match' => ['code' => 'SAVE100'],
                'attrs' => $this->base([
                    'name' => '[Demo] Coupon SAVE100',
                    'code' => 'SAVE100',
                    'description' => 'Example: Coupon code with usage + per-customer limits.',
                    'type' => PromotionType::Coupon,
                    'discount_type' => PromotionDiscountType::Fixed,
                    'discount_value' => 100,
                    'minimum_subtotal' => 400,
                    'usage_limit' => 200,
                    'usage_limit_per_customer' => 1,
                    'priority' => 55,
                    'customer_message' => 'SAVE100 applied.',
                    'internal_note' => 'Demo: coupon + global + per-customer limits.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Stackable Festival 5%'],
                'attrs' => $this->base([
                    'name' => '[Demo] Stackable Festival 5%',
                    'description' => 'Example: Stackable automatic percentage offer.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 5,
                    'stackable' => true,
                    'starts_at' => now()->subDay()->startOfDay(),
                    'ends_at' => now()->addDays(10)->endOfDay(),
                    'priority' => 5,
                    'customer_message' => 'Stackable 5% festival off.',
                    'internal_note' => 'Demo: stackable=true.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Non-Stackable 10%'],
                'attrs' => $this->base([
                    'name' => '[Demo] Non-Stackable 10%',
                    'description' => 'Example: Non-stackable order-level percentage.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 10,
                    'stackable' => false,
                    'priority' => 60,
                    'customer_message' => 'Non-stackable 10% off.',
                    'internal_note' => 'Demo: stackable=false.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Inactive Welcome 20%'],
                'attrs' => $this->base([
                    'name' => '[Demo] Inactive Welcome 20%',
                    'code' => 'DEMOWELCOME20',
                    'description' => 'Example: Inactive coupon (admin UI).',
                    'type' => PromotionType::Coupon,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 20,
                    'is_active' => false,
                    'first_order_only' => true,
                    'priority' => 0,
                    'customer_message' => 'Welcome 20% (inactive).',
                    'internal_note' => 'Demo: is_active=false.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Expired Summer Special'],
                'attrs' => $this->base([
                    'name' => '[Demo] Expired Summer Special',
                    'description' => 'Example: Date window already ended.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 8,
                    'starts_at' => now()->subMonths(2),
                    'ends_at' => now()->subMonth(),
                    'priority' => 4,
                    'customer_message' => 'Expired summer special.',
                    'internal_note' => 'Demo: expired date window.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Future Scheduled Promo'],
                'attrs' => $this->base([
                    'name' => '[Demo] Future Scheduled Promo',
                    'description' => 'Example: Starts in the future.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Fixed,
                    'discount_value' => 30,
                    'starts_at' => now()->addDays(14)->startOfDay(),
                    'ends_at' => now()->addDays(28)->endOfDay(),
                    'priority' => 8,
                    'customer_message' => 'Upcoming ₹30 off.',
                    'internal_note' => 'Demo: future starts_at.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Cap Max Discount 15%'],
                'attrs' => $this->base([
                    'name' => '[Demo] Cap Max Discount 15%',
                    'code' => 'DEMOCAP15',
                    'description' => 'Example: Percentage with maximum discount amount.',
                    'type' => PromotionType::Coupon,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 15,
                    'maximum_discount_amount' => 150,
                    'usage_limit' => 500,
                    'usage_limit_per_customer' => 2,
                    'priority' => 42,
                    'customer_message' => '15% off capped at ₹150.',
                    'internal_note' => 'Demo: maximum_discount_amount.',
                ]),
            ],
            [
                'match' => ['name' => '[Demo] Espresso Product Discount'],
                'attrs' => $this->base([
                    'name' => '[Demo] Espresso Product Discount',
                    'description' => 'Example: Product-specific promotion (not all products).',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 20,
                    'applies_to_all_products' => false,
                    'priority' => 48,
                    'customer_message' => '20% off Espresso.',
                    'internal_note' => 'Demo: product targeting via promotion_product.',
                ]),
                'products' => $espresso ? [$espresso->getKey()] : [],
            ],
            [
                'match' => ['name' => '[Demo] Cappuccino Category Discount'],
                'attrs' => $this->base([
                    'name' => '[Demo] Cappuccino Category Discount',
                    'description' => 'Example: Category-specific Hot Coffee discount.',
                    'type' => PromotionType::Automatic,
                    'discount_type' => PromotionDiscountType::Percentage,
                    'discount_value' => 10,
                    'applies_to_all_products' => false,
                    'priority' => 47,
                    'customer_message' => '10% off Hot Coffee category.',
                    'internal_note' => 'Demo: category targeting via promotion_product_category.',
                ]),
                'categories' => $hotCoffee ? [$hotCoffee->getKey()] : [],
            ],
            [
                'match' => ['name' => '[Demo] Buy Coffee Product Focus'],
                'attrs' => $this->base([
                    'name' => '[Demo] Buy Coffee Product Focus',
                    'code' => 'DEMOCOFFEE',
                    'description' => 'Example: Coupon limited to Cappuccino product line.',
                    'type' => PromotionType::Coupon,
                    'discount_type' => PromotionDiscountType::Fixed,
                    'discount_value' => 35,
                    'applies_to_all_products' => false,
                    'priority' => 49,
                    'customer_message' => 'DEMOCOFFEE: ₹35 off eligible coffee.',
                    'internal_note' => 'Demo: coupon + product restriction.',
                ]),
                'products' => array_values(array_filter([
                    $cappuccino?->getKey(),
                    $espresso?->getKey(),
                ])),
            ],
        ];

        foreach ($definitions as $definition) {
            $promotion = $this->upsert($definition['attrs'], $definition['match']);

            if (! ($definition['attrs']['applies_to_all_products'] ?? true)) {
                $promotion->products()->sync($definition['products'] ?? []);
                $promotion->productCategories()->sync($definition['categories'] ?? []);
            } else {
                $promotion->products()->detach();
                $promotion->productCategories()->detach();
            }
        }
    }

    protected function seedLegacyOffers(): void
    {
        $this->upsert($this->base([
            'name' => 'Dining 10%',
            'description' => 'Automatic 10% off for dine-in orders.',
            'type' => PromotionType::Automatic,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 10,
            'priority' => 10,
            'fulfilment_scope' => PromotionFulfilmentScope::Dining,
            'customer_message' => '10% dining discount applied.',
            'internal_note' => 'Demo automatic dining offer.',
        ]), ['name' => 'Dining 10%']);

        $this->upsert($this->base([
            'name' => 'Festival Coffee Offer',
            'description' => 'Seasonal automatic 5% off coffee orders.',
            'type' => PromotionType::Automatic,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 5,
            'starts_at' => now()->subDay()->startOfDay(),
            'ends_at' => now()->addDays(7)->endOfDay(),
            'priority' => 20,
            'stackable' => true,
            'customer_message' => 'Festival 5% off applied.',
            'internal_note' => 'Demo festival window: yesterday through +7 days.',
        ]), ['name' => 'Festival Coffee Offer']);

        $this->upsert($this->base([
            'name' => 'Bulk ₹100 Off',
            'code' => 'BULK500',
            'description' => '₹100 off when cart subtotal is at least ₹500.',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Fixed,
            'discount_value' => 100,
            'minimum_subtotal' => 500,
            'usage_limit_per_customer' => 5,
            'priority' => 50,
            'customer_message' => 'BULK500: ₹100 off applied.',
            'internal_note' => 'Demo coupon BULK500.',
        ]), ['code' => 'BULK500']);

        $this->upsert($this->base([
            'name' => 'Diwali 15%',
            'code' => 'DIWALI15',
            'description' => '15% off with a maximum discount cap.',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 15,
            'starts_at' => now()->subDay()->startOfDay(),
            'ends_at' => now()->addDays(14)->endOfDay(),
            'maximum_discount_amount' => 150,
            'usage_limit' => 1000,
            'usage_limit_per_customer' => 1,
            'priority' => 40,
            'customer_message' => 'DIWALI15: up to ₹150 off.',
            'internal_note' => 'Demo coupon DIWALI15 with max cap.',
        ]), ['code' => 'DIWALI15']);

        $this->upsert($this->base([
            'name' => 'Inactive Welcome Offer',
            'code' => 'WELCOME20',
            'description' => 'Inactive coupon kept for admin UI demos.',
            'type' => PromotionType::Coupon,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 20,
            'usage_limit_per_customer' => 1,
            'priority' => 0,
            'is_active' => false,
            'first_order_only' => true,
            'customer_message' => 'Welcome 20% off.',
            'internal_note' => 'Demo inactive coupon.',
        ]), ['code' => 'WELCOME20']);

        $this->upsert($this->base([
            'name' => 'Expired Summer Special',
            'description' => 'Expired automatic offer for admin UI demos.',
            'type' => PromotionType::Automatic,
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 8,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
            'priority' => 5,
            'customer_message' => 'Summer special 8% off.',
            'internal_note' => 'Demo expired automatic offer.',
        ]), ['name' => 'Expired Summer Special']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function base(array $overrides): array
    {
        return array_merge([
            'code' => null,
            'description' => null,
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
            'fulfilment_scope' => PromotionFulfilmentScope::Any,
            'weekdays' => null,
            'daily_starts_at' => null,
            'daily_ends_at' => null,
            'customer_message' => null,
            'internal_note' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $matchOn
     */
    protected function upsert(array $attributes, array $matchOn): Promotion
    {
        $promotion = Promotion::query()->withTrashed()->firstOrNew($matchOn);
        $promotion->fill($attributes);
        $promotion->deleted_at = null;
        $promotion->save();

        return $promotion;
    }
}
