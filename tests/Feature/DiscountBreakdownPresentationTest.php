<?php

namespace Tests\Feature;

use App\Enums\CustomerRewardType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\PromotionDiscountType;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\OrderPromotion;
use App\Models\OrderRewardRedemption;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Support\CustomerDiscountLines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiscountBreakdownPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_dine_in_promo_caps_at_subtotal_like_nine_fifty_example(): void
    {
        $this->enableDining();
        $this->setTax(enabled: false, percent: '0.00');

        Promotion::factory()->automatic()->dineIn()->fixed(25)->create([
            'name' => '[Demo] Dine-In Scope Promo',
            'customer_message' => '₹25 dine-in discount.',
            'priority' => 13,
        ]);

        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('4.75');

        $dining = app(DiningSessionServiceInterface::class);
        $session = $dining->startSession($table, $customer, $customer, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $customer);
        $dining->placeRound($session, $customer);
        $dining->addDraftItem($session->fresh(), (int) $variant->id, 1, $customer);
        $dining->placeRound($session->fresh(), $customer);
        $session = $dining->generateFinalBill($session->fresh(), $customer);

        $this->assertSame('9.50', (string) $session->subtotal_amount);
        $this->assertSame('9.50', (string) $session->discount_amount);
        $this->assertSame('0.00', (string) $session->total_amount);
        $this->assertSame(PaymentStatus::Pending, $session->payment_status);

        $bill = $dining->finalizedBill($session->fresh());
        $this->assertSame('9.50', $bill['discount']);
        $this->assertCount(1, $bill['discounts']);
        $this->assertSame('₹25 dine-in discount.', $bill['discounts'][0]['name']);
        $this->assertSame('9.50', $bill['discounts'][0]['amount']);
        $this->assertSame('promotion', $bill['discounts'][0]['type']);

        $lineSum = '0.00';
        foreach ($bill['discounts'] as $line) {
            $lineSum = bcadd($lineSum, $line['amount'], 2);
        }
        $this->assertSame($bill['discount'], $lineSum);

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.dining.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.totals.subtotal', '9.50')
            ->assertJsonPath('data.totals.discount', '9.50')
            ->assertJsonPath('data.totals.total', '0.00')
            ->assertJsonPath('data.discounts.0.name', '₹25 dine-in discount.')
            ->assertJsonPath('data.discounts.0.amount', '9.50')
            ->assertJsonPath('data.final_bill.discounts.0.name', '₹25 dine-in discount.');
    }

    public function test_dining_automatic_percentage_exposes_snapshot_name_not_generic_discount(): void
    {
        $this->enableDining();
        $this->setTax(enabled: false, percent: '0.00');

        Promotion::factory()->automatic()->dineIn()->percentage(100)->create([
            'name' => 'Complimentary Dining',
            'customer_message' => 'Complimentary Dining',
            'priority' => 90,
        ]);

        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('40.00');

        $dining = app(DiningSessionServiceInterface::class);
        $session = $dining->startSession($table, $customer, $customer, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $customer);
        $dining->placeRound($session, $customer);
        $session = $dining->generateFinalBill($session->fresh(), $customer);

        $this->assertSame('40.00', (string) $session->discount_amount);
        $this->assertSame('0.00', (string) $session->total_amount);

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.dining.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.discounts.0.name', 'Complimentary Dining')
            ->assertJsonPath('data.discounts.0.amount', '40.00');
    }

    public function test_retail_checkout_and_order_expose_coupon_name_and_code(): void
    {
        $this->setTax(enabled: false, percent: '0.00');

        Promotion::factory()->coupon('WELCOME20')->percentage(20)->create([
            'name' => 'Welcome Offer',
            'customer_message' => 'Welcome Offer',
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makeVariant('100.00'));

        $this->postJson(route('api.v1.cart.promo-code.apply'), [
            'promo_code' => 'WELCOME20',
            'fulfilment_method' => 'takeaway',
        ])->assertOk();

        $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->assertJsonPath('meta.summary.discount_total', '20.00')
            ->assertJsonPath('meta.summary.discounts.0.name', 'Welcome Offer')
            ->assertJsonPath('meta.summary.discounts.0.code', 'WELCOME20')
            ->assertJsonPath('meta.summary.discounts.0.amount', '20.00')
            ->assertJsonPath('meta.summary.discounts.0.type', 'promotion');

        $token = (string) $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->json('meta.checkout_token');

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
            ->assertJsonPath('data.discounts.0.name', 'Welcome Offer')
            ->assertJsonPath('data.discounts.0.code', 'WELCOME20')
            ->assertJsonPath('data.discounts.0.amount', '20.00')
            ->json('data.id');

        $this->getJson(route('api.v1.orders.show', $orderId))
            ->assertOk()
            ->assertJsonPath('data.discounts.0.name', 'Welcome Offer')
            ->assertJsonPath('data.discounts.0.code', 'WELCOME20');
    }

    public function test_stacked_promotion_lines_sum_to_discount_total(): void
    {
        $this->setTax(enabled: false, percent: '0.00');

        Promotion::factory()->automatic()->percentage(5)->create([
            'name' => 'Festival 5%',
            'customer_message' => 'Festival 5%',
            'stackable' => true,
            'priority' => 10,
        ]);
        Promotion::factory()->automatic()->percentage(10)->create([
            'name' => 'Happy Hour',
            'customer_message' => 'Happy Hour',
            'stackable' => true,
            'priority' => 20,
        ]);

        $customer = User::factory()->customer()->create();
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makeVariant('200.00'));

        $summary = $this->getJson(route('api.v1.checkout.summary', ['fulfilment_method' => 'takeaway']))
            ->assertOk()
            ->json('meta.summary');

        $this->assertGreaterThanOrEqual(2, count($summary['discounts']));
        $lineSum = '0.00';
        foreach ($summary['discounts'] as $line) {
            $lineSum = bcadd($lineSum, (string) $line['amount'], 2);
        }
        $this->assertSame($summary['discount_total'], $lineSum);
        $names = array_column($summary['discounts'], 'name');
        $this->assertContains('Festival 5%', $names);
        $this->assertContains('Happy Hour', $names);
    }

    public function test_missing_historical_snapshot_name_falls_back_to_discount(): void
    {
        $lines = CustomerDiscountLines::fromPromotionSnapshots([
            (object) [
                'name_snapshot' => '',
                'code_snapshot' => null,
                'discount_amount' => '12.00',
            ],
        ]);

        $this->assertSame('Discount', $lines[0]['name']);
        $this->assertSame('12.00', $lines[0]['amount']);
    }

    public function test_order_discounts_include_referral_coupon_redemption_snapshot(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'subtotal' => '100.00',
            'discount_total' => '15.00',
            'total_amount' => '85.00',
        ]);

        OrderPromotion::query()->create([
            'order_id' => $order->id,
            'promotion_id' => Promotion::factory()->create()->id,
            'name_snapshot' => 'Weekend Offer',
            'code_snapshot' => null,
            'discount_type_snapshot' => PromotionDiscountType::Percentage,
            'discount_value_snapshot' => '10.00',
            'discount_amount' => '10.00',
            'sort_order' => 0,
        ]);

        OrderRewardRedemption::query()->create([
            'order_id' => $order->id,
            'customer_reward_id' => null,
            'reward_type' => CustomerRewardType::Coupon,
            'description_snapshot' => 'Referral Reward',
            'benefit_amount' => '5.00',
            'original_amount' => '5.00',
            'coupon_code_snapshot' => 'REF5',
        ]);

        Sanctum::actingAs($customer);
        $payload = $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->json('data.discounts');

        $this->assertCount(2, $payload);
        $this->assertSame('Weekend Offer', $payload[0]['name']);
        $this->assertSame('10.00', $payload[0]['amount']);
        $this->assertSame('promotion', $payload[0]['type']);
        $this->assertSame('Referral Reward', $payload[1]['name']);
        $this->assertSame('REF5', $payload[1]['code']);
        $this->assertSame('5.00', $payload[1]['amount']);
        $this->assertSame('referral', $payload[1]['type']);

        $sum = bcadd($payload[0]['amount'], $payload[1]['amount'], 2);
        $this->assertSame('15.00', $sum);
    }

    public function test_zero_total_dining_bill_stays_awaiting_payment_not_auto_paid(): void
    {
        $this->enableDining();
        $this->setTax(enabled: false, percent: '0.00');

        Promotion::factory()->automatic()->dineIn()->percentage(100)->create([
            'name' => 'Free Table',
            'customer_message' => 'Free Table',
        ]);

        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makeVariant('30.00');

        $dining = app(DiningSessionServiceInterface::class);
        $session = $dining->startSession($table, $customer, $customer, ['guest_count' => 1]);
        $dining->addDraftItem($session, (int) $variant->id, 1, $customer);
        $dining->placeRound($session, $customer);
        $session = $dining->generateFinalBill($session->fresh(), $customer);

        $this->assertSame('0.00', (string) $session->total_amount);
        $this->assertSame(PaymentStatus::Pending, $session->payment_status);
        $this->assertNull($session->paid_at);
    }

    protected function enableDining(): void
    {
        $this->putSetting(WebsiteSettingKey::FulfilmentDineInEnabled, '1');
        $this->putSetting(WebsiteSettingKey::OrderingManualClosed, '0');
    }

    protected function setTax(bool $enabled, string $percent, bool $inclusive = false): void
    {
        $this->putSetting(WebsiteSettingKey::TaxEnabled, $enabled ? '1' : '0');
        $this->putSetting(WebsiteSettingKey::TaxLabel, 'GST');
        $this->putSetting(WebsiteSettingKey::TaxPercent, $percent);
        $this->putSetting(WebsiteSettingKey::TaxInclusive, $inclusive ? '1' : '0');
    }

    protected function putSetting(WebsiteSettingKey $key, ?string $value): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'section' => $key->section(),
                'value_type' => $key->valueType(),
                'value' => $value,
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

    protected function makeVariant(string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '250.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);
    }
}
