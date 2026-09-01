<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\ProductServingUnit;
use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFulfilmentScope;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\OrderPromotion;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Cart\CartServiceInterface;
use App\Services\Promotion\PromotionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_dine_in_ten_percent_applies_for_dine_in_fulfilment(): void
    {
        Promotion::factory()->automatic()->dineIn()->percentage(10)->create([
            'name' => 'Dine-in 10%',
            'customer_message' => 'Dine-in discount applied.',
        ]);

        $customer = User::factory()->customer()->create();
        $variant = $this->makePurchasableVariant('100.00');
        Sanctum::actingAs($customer);

        $this->addVariantToCart($variant);

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'dine_in']))
            ->assertOk()
            ->assertJsonPath('meta.summary.subtotal', '100.00')
            ->assertJsonPath('meta.summary.discount_total', '10.00')
            ->assertJsonPath('meta.summary.discounts.0.amount', '10.00')
            ->assertJsonPath('meta.summary.discounts.0.name', 'Dine-in discount applied.');
    }

    public function test_dine_in_discount_not_applied_for_takeaway(): void
    {
        Promotion::factory()->automatic()->dineIn()->percentage(10)->create([
            'name' => 'Dine-in only',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '0.00')
            ->assertJsonPath('meta.summary.discounts', []);
    }

    public function test_delivery_scope_applies_only_for_delivery_fulfilment(): void
    {
        Promotion::factory()->automatic()->percentage(15)->create([
            'name' => 'Delivery 15%',
            'fulfilment_scope' => PromotionFulfilmentScope::Delivery,
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('80.00'));

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'delivery']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '12.00');

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '0.00');
    }

    public function test_valid_coupon_applies_with_normalized_case(): void
    {
        Promotion::factory()->coupon('SAVE10')->percentage(10)->create([
            'name' => 'Save 10',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('50.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'save10',
            'fulfilment_method' => 'takeaway',
        ])
            ->assertOk()
            ->assertJsonPath('meta.summary.promo_code', 'SAVE10')
            ->assertJsonPath('meta.summary.discount_total', '5.00')
            ->assertJsonPath('meta.summary.discounts.0.code', 'SAVE10');
    }

    public function test_invalid_coupon_is_rejected(): void
    {
        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('40.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'NOPE123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['promo_code']);

        $this->getJson(route('api.v1.cart.show'))
            ->assertOk()
            ->assertJsonPath('meta.summary.promo_code', null)
            ->assertJsonPath('meta.summary.discount_total', '0.00');
    }

    public function test_inactive_and_expired_coupons_are_rejected(): void
    {
        Promotion::factory()->coupon('DEAD')->inactive()->percentage(20)->create([
            'name' => 'Inactive coupon',
        ]);
        Promotion::factory()->coupon('OLDIE')->expired()->percentage(20)->create([
            'name' => 'Expired coupon',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('60.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), ['promo_code' => 'DEAD'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['promo_code']);

        $this->postJson(route('api.v1.cart.promo-code.apply'), ['promo_code' => 'OLDIE'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['promo_code']);
    }

    public function test_minimum_subtotal_is_enforced(): void
    {
        Promotion::factory()->coupon('BULK')->fixed(50)->create([
            'name' => 'Bulk save',
            'minimum_subtotal' => '100.00',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('40.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'BULK',
            'fulfilment_method' => 'takeaway',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['promo_code']);

        $this->addVariantToCart($this->makePurchasableVariant('70.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'BULK',
            'fulfilment_method' => 'takeaway',
        ])
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '50.00');
    }

    public function test_maximum_discount_cap_limits_percentage(): void
    {
        Promotion::factory()->automatic()->percentage(50)->create([
            'name' => 'Capped half off',
            'maximum_discount_amount' => '15.00',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '15.00');
    }

    public function test_selected_product_and_category_eligibility(): void
    {
        $eligibleVariant = $this->makePurchasableVariant('40.00');
        $ineligibleVariant = $this->makePurchasableVariant('40.00');
        $categoryVariant = $this->makePurchasableVariant('30.00');

        $productPromo = Promotion::factory()->automatic()->percentage(10)->create([
            'name' => 'Product only',
            'applies_to_all_products' => false,
            'priority' => 10,
            'stackable' => false,
        ]);
        $productPromo->products()->attach($eligibleVariant->product_id);

        $categoryPromo = Promotion::factory()->coupon('CAT10')->percentage(10)->create([
            'name' => 'Category only',
            'applies_to_all_products' => false,
        ]);
        $categoryPromo->productCategories()->attach($categoryVariant->product->product_category_id);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);

        $this->addVariantToCart($eligibleVariant);
        $this->addVariantToCart($ineligibleVariant);

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.subtotal', '80.00')
            ->assertJsonPath('meta.summary.discount_total', '4.00');

        $this->deleteJson(route('api.v1.cart.clear'))->assertOk();
        $this->addVariantToCart($categoryVariant);

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'CAT10',
            'fulfilment_method' => 'takeaway',
        ])
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '3.00');

        $this->deleteJson(route('api.v1.cart.clear'))->assertOk();
        $this->addVariantToCart($ineligibleVariant);

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'CAT10',
            'fulfilment_method' => 'takeaway',
        ])->assertUnprocessable();
    }

    public function test_customer_specific_eligibility(): void
    {
        $allowed = User::factory()->customer()->create();
        $denied = User::factory()->customer()->create();

        $promotion = Promotion::factory()->coupon('VIP20')->percentage(20)->create([
            'name' => 'VIP only',
            'applies_to_all_customers' => false,
        ]);
        $promotion->customers()->attach($allowed->id);

        Sanctum::actingAs($denied);
        $this->addVariantToCart($this->makePurchasableVariant('50.00'));
        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'VIP20',
            'fulfilment_method' => 'takeaway',
        ])->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($allowed);
        $this->addVariantToCart($this->makePurchasableVariant('50.00'));
        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'VIP20',
            'fulfilment_method' => 'takeaway',
        ])
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '10.00');
    }

    public function test_usage_limit_and_per_customer_limit_are_enforced(): void
    {
        $promotion = Promotion::factory()->coupon('ONCE')->fixed(5)->create([
            'name' => 'Once only',
            'usage_limit' => 1,
            'usage_limit_per_customer' => 1,
        ]);

        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();

        $order = Order::factory()->create([
            'customer_id' => $other->id,
            'status' => OrderStatus::PendingPayment,
        ]);
        OrderPromotion::query()->create([
            'order_id' => $order->id,
            'promotion_id' => $promotion->id,
            'name_snapshot' => $promotion->name,
            'code_snapshot' => $promotion->code,
            'discount_type_snapshot' => $promotion->discount_type->value,
            'discount_value_snapshot' => $promotion->discount_value,
            'discount_amount' => '5.00',
            'sort_order' => 0,
        ]);

        $service = $this->app->make(PromotionServiceInterface::class);
        $this->assertSame(1, $service->usageCount($promotion));

        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('20.00'));
        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'ONCE',
            'fulfilment_method' => 'takeaway',
        ])->assertUnprocessable();

        $perCustomer = Promotion::factory()->coupon('ME1')->fixed(3)->create([
            'name' => 'Per customer once',
            'usage_limit' => null,
            'usage_limit_per_customer' => 1,
        ]);
        $mine = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PaymentConfirmed,
        ]);
        OrderPromotion::query()->create([
            'order_id' => $mine->id,
            'promotion_id' => $perCustomer->id,
            'name_snapshot' => $perCustomer->name,
            'code_snapshot' => $perCustomer->code,
            'discount_type_snapshot' => $perCustomer->discount_type->value,
            'discount_value_snapshot' => $perCustomer->discount_value,
            'discount_amount' => '3.00',
            'sort_order' => 0,
        ]);

        $this->assertSame(1, $service->usageCountForCustomer($perCustomer, $customer));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'ME1',
            'fulfilment_method' => 'takeaway',
        ])->assertUnprocessable();
    }

    public function test_fixed_discount_cannot_exceed_eligible_subtotal(): void
    {
        Promotion::factory()->coupon('HUGE')->fixed(500)->create([
            'name' => 'Huge fixed',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('25.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'HUGE',
            'fulfilment_method' => 'takeaway',
        ])
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '25.00')
            ->assertJsonPath('meta.summary.total', '0.00');
    }

    public function test_non_stackable_promotions_keep_best_eligible_only(): void
    {
        Promotion::factory()->automatic()->percentage(10)->create([
            'name' => 'Ten percent',
            'priority' => 1,
            'stackable' => false,
        ]);
        Promotion::factory()->automatic()->fixed(15)->create([
            'name' => 'Fifteen rupees',
            'priority' => 5,
            'stackable' => false,
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));

        $summary = $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '15.00')
            ->json('meta.summary');

        $this->assertCount(1, $summary['discounts']);
        $this->assertSame('15.00', $summary['discounts'][0]['amount']);
    }

    public function test_stackable_promotions_apply_together_only_when_all_are_stackable(): void
    {
        Promotion::factory()->automatic()->percentage(10)->create([
            'name' => 'Stack A',
            'priority' => 20,
            'stackable' => true,
        ]);
        Promotion::factory()->automatic()->fixed(5)->create([
            'name' => 'Stack B',
            'priority' => 10,
            'stackable' => true,
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '15.00');

        Promotion::factory()->automatic()->fixed(20)->create([
            'name' => 'Non-stack winner',
            'priority' => 1,
            'stackable' => false,
        ]);

        $mixed = $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '20.00')
            ->json('meta.summary');

        $this->assertCount(1, $mixed['discounts']);
    }

    public function test_fulfilment_change_recalculates_discounts_on_cart_and_checkout_summary(): void
    {
        Promotion::factory()->automatic()->dineIn()->percentage(10)->create([
            'name' => 'Dine-in auto',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));

        $cart = $this->app->make(CartServiceInterface::class)->getForCustomer($customer);
        $cartService = $this->app->make(CartServiceInterface::class);

        $dineIn = $cartService->summarize($cart, 'dine_in');
        $takeaway = $cartService->summarize($cart, 'takeaway');

        $this->assertSame('10.00', $dineIn['discount_total']);
        $this->assertSame('0.00', $takeaway['discount_total']);

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'dine_in']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '10.00');

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '0.00');
    }

    public function test_inclusive_gst_applies_on_discounted_taxable_amount(): void
    {
        $this->setTaxSettings(enabled: true, percent: '5.00', inclusive: true);

        Promotion::factory()->automatic()->percentage(10)->create([
            'name' => 'Ten off inclusive',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('105.00'));

        // Discount 10.50 → taxable 94.50; inclusive GST = 94.50 * 5 / 105 = 4.50; cafe total stays 94.50.
        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.subtotal', '105.00')
            ->assertJsonPath('meta.summary.discount_total', '10.50')
            ->assertJsonPath('meta.summary.tax.inclusive', true)
            ->assertJsonPath('meta.summary.tax.taxable_amount', '94.50')
            ->assertJsonPath('meta.summary.tax.amount', '4.50')
            ->assertJsonPath('meta.summary.total', '94.50');
    }

    public function test_exclusive_gst_applies_on_discounted_taxable_amount(): void
    {
        $this->setTaxSettings(enabled: true, percent: '5.00', inclusive: false);

        Promotion::factory()->automatic()->percentage(10)->create([
            'name' => 'Ten off exclusive',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));

        // Discount 10.00 → taxable 90.00; exclusive GST 4.50 → total 94.50.
        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '10.00')
            ->assertJsonPath('meta.summary.tax.inclusive', false)
            ->assertJsonPath('meta.summary.tax.taxable_amount', '90.00')
            ->assertJsonPath('meta.summary.tax.amount', '4.50')
            ->assertJsonPath('meta.summary.total', '94.50');
    }

    public function test_discount_evaluation_is_merchandise_only_delivery_fee_not_wired_into_orders(): void
    {
        // Delivery fee is not applied at order creation today (delivery_fee_amount stays null).
        // Promotions evaluate only merchandise line subtotals.
        Promotion::factory()->automatic()->percentage(10)->create([
            'name' => 'Merch only',
            'fulfilment_scope' => PromotionFulfilmentScope::Delivery,
        ]);

        $result = $this->app->make(PromotionServiceInterface::class)->evaluate([
            'fulfilment' => OrderFulfilmentMethod::Delivery,
            'items' => [[
                'product_id' => 1,
                'product_category_id' => 1,
                'quantity' => 1,
                'unit_price' => '100.00',
                'line_subtotal' => '100.00',
            ]],
        ]);

        $this->assertSame('10.00', $result['discount_total']);

        $customer = User::factory()->customer()->create(['phone' => '9111000099']);
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));

        $token = (string) $this->getJson(route('api.v1.checkout.summary', [
            'fulfilment_method' => 'delivery',
        ]))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'delivery',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_address' => '1 Coffee Road',
            'delivery_phone' => $customer->phone,
        ])
            ->assertCreated()
            ->assertJsonPath('data.discount_total', '10.00')
            ->assertJsonPath('data.delivery_fee_amount', null)
            ->assertJsonPath('data.total_amount', '90.00');
    }

    public function test_order_promotion_snapshots_retained_after_promotion_edit_and_soft_delete(): void
    {
        $promotion = Promotion::factory()->coupon('SNAP10')->percentage(10)->create([
            'name' => 'Snapshot Offer',
            'customer_message' => 'Snapshot Offer',
        ]);

        $customer = User::factory()->customer()->create(['phone' => '9111000088']);
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('50.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'SNAP10',
            'fulfilment_method' => 'takeaway',
        ])->assertOk();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $orderId = (int) $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
        ])
            ->assertCreated()
            ->assertJsonPath('data.promotions.0.name', 'Snapshot Offer')
            ->assertJsonPath('data.promotions.0.code', 'SNAP10')
            ->assertJsonPath('data.promotions.0.amount', '5.00')
            ->json('data.id');

        $promotion->update([
            'name' => 'Renamed Offer',
            'customer_message' => 'Renamed Offer',
            'discount_value' => 50,
            'code' => 'CHANGED',
        ]);
        $promotion->delete();

        $this->assertSoftDeleted('promotions', ['id' => $promotion->id]);

        $this->getJson(route('api.v1.orders.show', $orderId))
            ->assertOk()
            ->assertJsonPath('data.promotions.0.name', 'Snapshot Offer')
            ->assertJsonPath('data.promotions.0.code', 'SNAP10')
            ->assertJsonPath('data.promotions.0.discount_value', '10.00')
            ->assertJsonPath('data.promotions.0.amount', '5.00')
            ->assertJsonPath('data.discount_total', '5.00');
    }

    public function test_cancelled_and_rejected_orders_do_not_consume_usage_permanently(): void
    {
        $promotion = Promotion::factory()->coupon('RETRY')->fixed(8)->create([
            'name' => 'Retryable',
            'usage_limit' => 1,
            'usage_limit_per_customer' => 1,
        ]);

        $customer = User::factory()->customer()->create();
        $cancelled = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        OrderPromotion::query()->create([
            'order_id' => $cancelled->id,
            'promotion_id' => $promotion->id,
            'name_snapshot' => $promotion->name,
            'code_snapshot' => $promotion->code,
            'discount_type_snapshot' => PromotionDiscountType::Fixed->value,
            'discount_value_snapshot' => '8.00',
            'discount_amount' => '8.00',
            'sort_order' => 0,
        ]);

        $rejected = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Rejected,
            'rejected_at' => now(),
        ]);
        OrderPromotion::query()->create([
            'order_id' => $rejected->id,
            'promotion_id' => $promotion->id,
            'name_snapshot' => $promotion->name,
            'code_snapshot' => $promotion->code,
            'discount_type_snapshot' => PromotionDiscountType::Fixed->value,
            'discount_value_snapshot' => '8.00',
            'discount_amount' => '8.00',
            'sort_order' => 0,
        ]);

        $service = $this->app->make(PromotionServiceInterface::class);
        $this->assertSame(0, $service->usageCount($promotion));
        $this->assertSame(0, $service->usageCountForCustomer($promotion, $customer));

        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('30.00'));
        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'RETRY',
            'fulfilment_method' => 'takeaway',
        ])
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '8.00');
    }

    public function test_forged_client_discount_amounts_are_ignored_and_only_promo_code_is_accepted(): void
    {
        Promotion::factory()->coupon('REAL5')->fixed(5)->create([
            'name' => 'Real five',
        ]);

        $customer = User::factory()->customer()->create(['phone' => '9111000077']);
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('40.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'REAL5',
            'fulfilment_method' => 'takeaway',
        ])->assertOk();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
            'discount_total' => '39.00',
            'discount_amount' => '39.00',
            'promo_code' => 'FORGED',
        ])
            ->assertCreated()
            ->assertJsonPath('data.discount_total', '5.00')
            ->assertJsonPath('data.promotions.0.code', 'REAL5')
            ->assertJsonPath('data.promotions.0.amount', '5.00')
            ->assertJsonPath('data.total_amount', '35.00');

        $this->assertDatabaseMissing('order_promotions', [
            'code_snapshot' => 'FORGED',
        ]);
    }

    public function test_promo_code_can_be_cleared_from_cart(): void
    {
        Promotion::factory()->coupon('CLEARME')->percentage(10)->create();

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('60.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'CLEARME',
            'fulfilment_method' => 'takeaway',
        ])->assertOk();

        $this->deleteJson(route('api.v1.cart.promo-code.clear'))
            ->assertOk()
            ->assertJsonPath('meta.summary.promo_code', null)
            ->assertJsonPath('meta.summary.discount_total', '0.00');
    }

    public function test_dine_in_checkout_persists_automatic_promotion_on_order(): void
    {
        $this->enableDineIn();
        $table = CafeTable::factory()->create(['is_active' => true, 'code' => 'P1']);

        Promotion::factory()->automatic()->dineIn()->percentage(10)->create([
            'name' => 'Table 10%',
            'customer_message' => 'Table 10%',
        ]);

        $customer = User::factory()->customer()->create(['phone' => '9111000066']);
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));

        $token = (string) $this->getJson(route('api.v1.checkout.summary', [
            'fulfilment_method' => 'dine_in',
        ]))->json('meta.checkout_token');

        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'dine_in',
            'cafe_table_id' => $table->id,
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
        ])
            ->assertCreated()
            ->assertJsonPath('data.discount_total', '10.00')
            ->assertJsonPath('data.promotions.0.name', 'Table 10%')
            ->assertJsonPath('data.total_amount', '90.00');
    }

    /**
     * @param  array{gstin?: ?string, legal?: ?string}  $extra
     */
    protected function setTaxSettings(
        bool $enabled,
        string $percent,
        bool $inclusive = false,
        ?string $gstin = null,
        ?string $legal = null,
    ): void {
        $map = [
            WebsiteSettingKey::TaxEnabled->value => $enabled ? '1' : '0',
            WebsiteSettingKey::TaxLabel->value => 'GST',
            WebsiteSettingKey::TaxPercent->value => $percent,
            WebsiteSettingKey::TaxInclusive->value => $inclusive ? '1' : '0',
            WebsiteSettingKey::TaxGstin->value => $gstin,
            WebsiteSettingKey::TaxLegalBusinessName->value => $legal,
        ];

        foreach ($map as $key => $value) {
            $enum = WebsiteSettingKey::from($key);
            WebsiteSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'section' => $enum->section(),
                    'value_type' => $enum->valueType(),
                    'value' => $value,
                ],
            );
        }
    }

    protected function enableDineIn(): void
    {
        $key = WebsiteSettingKey::FulfilmentDineInEnabled;
        WebsiteSetting::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'section' => $key->section(),
                'value_type' => $key->valueType(),
                'value' => '1',
            ],
        );
    }

    protected function addVariantToCart(ProductVariant $variant, int $quantity = 1): void
    {
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ])->assertCreated();
    }

    protected function makePurchasableVariant(string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create([
            'name' => fake()->unique()->words(2, true),
        ]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);
    }
}
