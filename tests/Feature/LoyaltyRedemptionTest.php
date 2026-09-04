<?php

namespace Tests\Feature;

use App\Enums\LoyaltyRewardStatus;
use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\PromotionDiscountType;
use App\Enums\WebsiteSettingKey;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Cart\CartServiceInterface;
use App\Services\Invoice\OrderInvoiceServiceInterface;
use App\Services\Loyalty\LoyaltyServiceInterface;
use App\Services\Order\OrderServiceInterface;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyRedemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('loyalty.enabled', true);
        config()->set('loyalty.effective_at', null);
        config()->set('loyalty.redemption.enabled', true);
        config()->set('loyalty.redemption.allow_with_promotions', true);
        config()->set('loyalty.referral_bridge.enabled', false);
        $this->setTaxSettings(false, '0.00', false);
    }

    public function test_available_rewards_and_insufficient_points(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 40,
        ]);
        $affordable = LoyaltyReward::factory()->fixed('20.00')->create([
            'name' => 'Small treat',
            'points_cost' => 30,
        ]);
        LoyaltyReward::factory()->fixed('50.00')->create([
            'name' => 'Big treat',
            'points_cost' => 100,
        ]);

        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));

        $this->getJson(route('api.v1.account.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('data.available_points', 40);

        $rewards = collect($this->getJson(route('api.v1.account.loyalty.rewards'))
            ->assertOk()
            ->assertJsonCount(2, 'data.rewards')
            ->json('data.rewards'));

        $this->assertTrue((bool) $rewards->firstWhere('id', $affordable->id)['eligible']);
        $this->assertFalse((bool) $rewards->firstWhere('points_cost', 100)['eligible']);
        $this->assertSame('insufficient_points', $rewards->firstWhere('points_cost', 100)['unavailable_reason']);
    }

    public function test_fixed_and_percentage_discount_redemption_and_snapshot(): void
    {
        $customer = User::factory()->customer()->create();
        $this->creditPoints($customer, 200);
        $variant = $this->makePurchasableVariant('100.00');
        $reward = LoyaltyReward::factory()->fixed('25.00')->create([
            'points_cost' => 50,
            'name' => '₹25 off',
        ]);

        $order = $this->placeOrderWithReward($customer, $variant, $reward);

        $this->assertSame('25.00', (string) $order->loyalty_discount_amount);
        $this->assertSame('₹25 off', $order->loyalty_reward_name_snapshot);
        $this->assertSame(50, (int) $order->loyalty_reward_points_cost_snapshot);
        $this->assertSame(150, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));
        $this->assertSame(50, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('lifetime_redeemed_points'));
        $this->assertDatabaseHas('loyalty_point_transactions', [
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::Redeem->value,
            'points' => -50,
        ]);

        $reward->update(['name' => 'Renamed later', 'config' => ['discount_amount' => '99.00']]);
        $this->assertSame('₹25 off', $order->fresh()->loyalty_reward_name_snapshot);
        $this->assertSame('25.00', (string) $order->fresh()->loyalty_discount_amount);

        $pctCustomer = User::factory()->customer()->create();
        $this->creditPoints($pctCustomer, 100);
        $pctReward = LoyaltyReward::factory()->percentage(10, 15)->create(['points_cost' => 40]);
        $pctOrder = $this->placeOrderWithReward($pctCustomer, $this->makePurchasableVariant('100.00'), $pctReward);
        $this->assertSame('10.00', (string) $pctOrder->loyalty_discount_amount);
    }

    public function test_free_base_product_and_min_spend_and_schedule(): void
    {
        $customer = User::factory()->customer()->create();
        $this->creditPoints($customer, 300);
        $variant = $this->makePurchasableVariant('80.00');
        $product = $variant->product;

        $free = LoyaltyReward::factory()->create([
            'reward_type' => LoyaltyRewardType::FreeBaseProduct,
            'points_cost' => 60,
            'config' => [],
            'minimum_spend' => '50.00',
        ]);
        $free->products()->sync([(int) $product->id]);

        $order = $this->placeOrderWithReward($customer, $variant, $free);
        $this->assertSame('80.00', (string) $order->loyalty_discount_amount);

        $scheduled = LoyaltyReward::factory()->fixed('10.00')->create([
            'points_cost' => 10,
            'starts_at' => now()->addDay(),
        ]);
        Sanctum::actingAs($customer);
        $this->addVariantToCart($this->makePurchasableVariant('100.00'));
        $this->postJson(route('api.v1.cart.loyalty-reward.apply'), ['loyalty_reward_id' => $scheduled->id])
            ->assertStatus(422);

        $minSpend = LoyaltyReward::factory()->fixed('10.00')->create([
            'points_cost' => 10,
            'minimum_spend' => '200.00',
        ]);
        $this->postJson(route('api.v1.cart.loyalty-reward.apply'), ['loyalty_reward_id' => $minSpend->id])
            ->assertStatus(422);
    }

    public function test_promotion_plus_loyalty_stacking_and_forged_value_ignored(): void
    {
        Promotion::factory()->automatic()->create([
            'name' => 'Auto 10%',
            'discount_type' => PromotionDiscountType::Percentage,
            'discount_value' => 10,
            'applies_to_all_products' => true,
        ]);

        $customer = User::factory()->customer()->create();
        $this->creditPoints($customer, 100);
        $reward = LoyaltyReward::factory()->fixed('20.00')->create(['points_cost' => 40]);
        $variant = $this->makePurchasableVariant('100.00');

        Sanctum::actingAs($customer);
        $this->addVariantToCart($variant);
        app(CartServiceInterface::class)->applyLoyaltyReward($customer, (int) $reward->id, 'takeaway');
        $summary = app(CartServiceInterface::class)->summarize(
            app(CartServiceInterface::class)->getForCustomer($customer),
            'takeaway',
        );

        $this->assertSame('10.00', $summary['discount_total']);
        $this->assertSame('20.00', $summary['loyalty_discount']);
        // 100 - 10 promo - 20 loyalty = 70 taxable basis (tax off)
        $this->assertSame('70.00', $summary['tax']['taxable_amount'] ?? $summary['total']);

        $order = $this->placeOrderWithReward($customer, $variant, $reward);
        $this->assertSame('10.00', (string) $order->discount_total);
        $this->assertSame('20.00', (string) $order->loyalty_discount_amount);
        $this->assertNotSame('999.00', (string) $order->loyalty_discount_amount);
    }

    public function test_failed_checkout_does_not_spend_points_and_cancel_restores(): void
    {
        $customer = User::factory()->customer()->create();
        $this->creditPoints($customer, 100);
        $reward = LoyaltyReward::factory()->fixed('15.00')->create(['points_cost' => 40]);
        $variant = $this->makePurchasableVariant('50.00');

        $this->assertSame(100, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));

        // Validation failure before order create: no ledger spend.
        try {
            $transfer = $this->makeTransfer($customer, $variant, $reward);
            $transfer->setItems([]);
            app(OrderServiceInterface::class)->store($customer, $transfer);
            $this->fail('Expected validation failure');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(100, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));
        $this->assertSame(0, LoyaltyPointTransaction::query()->where('type', LoyaltyTransactionType::Redeem->value)->count());

        $order = $this->placeOrderWithReward($customer, $variant, $reward);
        $this->assertSame(60, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));

        $admin = User::factory()->manager()->create();
        $transfer = app(OrderStatusTransitionTransferInterface::class);
        $transfer->setStatus(OrderStatus::Cancelled->value);
        $transfer->setNotes('Customer cancelled');
        app(OrderServiceInterface::class)->transition($order, $admin, $transfer);

        $this->assertSame(100, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));
        $this->assertSame(40, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('lifetime_redeemed_points'));

        $restore = app(LoyaltyServiceInterface::class)->restoreRedemptionForOrder($order->fresh());
        $this->assertSame('idempotent', $restore['reason']);
    }

    public function test_duplicate_redeem_idempotent_and_one_reward_per_order(): void
    {
        $customer = User::factory()->customer()->create();
        $this->creditPoints($customer, 200);
        $reward = LoyaltyReward::factory()->fixed('10.00')->create(['points_cost' => 25]);
        $order = $this->placeOrderWithReward($customer, $this->makePurchasableVariant('60.00'), $reward);

        $second = app(LoyaltyServiceInterface::class)->redeemForOrder($order, 25, [
            'reward_id' => (int) $reward->id,
            'name' => $reward->name,
            'reward_type' => $reward->reward_type->value,
            'discount_amount' => '10.00',
        ]);
        $this->assertSame('idempotent', $second['reason']);
        $this->assertSame(1, LoyaltyPointTransaction::query()->where('type', LoyaltyTransactionType::Redeem->value)->count());
        $this->assertSame(175, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));
    }

    public function test_admin_adjustments_and_authorization(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $loyalty = app(LoyaltyServiceInterface::class);

        $pos = $loyalty->adjustPoints($customer, $admin, 75, 'Promo goodwill', 'adj:test:pos');
        $this->assertTrue($pos['adjusted']);
        $this->assertSame(75, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));
        $this->assertSame(75, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('lifetime_adjusted_points'));

        $neg = $loyalty->adjustPoints($customer, $admin, -20, 'Correction', 'adj:test:neg');
        $this->assertTrue($neg['adjusted']);
        $this->assertSame(55, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));
        $this->assertSame(55, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('lifetime_adjusted_points'));

        try {
            $loyalty->adjustPoints($customer, $admin, 10, '   ');
            $this->fail('Expected mandatory reason');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->actingAs($barista, 'admin')
            ->post(route('administrator.users.loyalty-adjust', $customer), [
                'points' => 5,
                'reason' => 'Nope',
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.users.loyalty-adjust', $customer), [
                'points' => 5,
                'reason' => 'Admin top-up',
                'idempotency_key' => 'adj:http:1',
                'confirmed' => '1',
            ])
            ->assertRedirect();
    }

    public function test_post_earn_reversal_creates_debt_and_blocks_redemption_until_recovery(): void
    {
        $customer = User::factory()->customer()->create();
        $loyalty = app(LoyaltyServiceInterface::class);

        $earnOrder = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'taxable_amount' => '100.00',
            'tax_amount' => '0.00',
            'total_amount' => '100.00',
            'completed_at' => now(),
        ]);
        $loyalty->awardForOrder($earnOrder);
        $this->assertSame(100, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));

        $reward = LoyaltyReward::factory()->fixed('10.00')->create(['points_cost' => 80]);
        $this->placeOrderWithReward($customer, $this->makePurchasableVariant('50.00'), $reward);
        $this->assertSame(20, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));

        $reversal = $loyalty->reverseOrderAward($earnOrder);
        $this->assertTrue($reversal['reversed']);
        $account = LoyaltyAccount::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(-80, (int) $account->available_points);
        $this->assertSame(100, (int) $account->lifetime_earned_points);

        Sanctum::actingAs($customer);
        $payload = $this->getJson(route('api.v1.account.loyalty.show'))->assertOk()->json('data');
        $this->assertTrue($payload['has_points_debt']);
        $this->assertSame('Points adjustment pending', $payload['debt_message']);

        $blocked = LoyaltyReward::factory()->fixed('5.00')->create(['points_cost' => 10]);
        $this->addVariantToCart($this->makePurchasableVariant('40.00'));
        $this->postJson(route('api.v1.cart.loyalty-reward.apply'), ['loyalty_reward_id' => $blocked->id])
            ->assertStatus(422);

        $recovery = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'taxable_amount' => '100.00',
            'tax_amount' => '0.00',
            'total_amount' => '100.00',
            'completed_at' => now(),
        ]);
        $loyalty->awardForOrder($recovery);
        $this->assertSame(20, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));
    }

    public function test_invoice_shows_loyalty_discount_and_customer_isolation(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $this->creditPoints($customer, 100);
        $reward = LoyaltyReward::factory()->fixed('12.00')->create(['points_cost' => 30]);
        $order = $this->placeOrderWithReward($customer, $this->makePurchasableVariant('40.00'), $reward);

        $invoice = app(OrderInvoiceServiceInterface::class)->build($order);
        $this->assertSame('12.00', $invoice->loyaltyDiscountAmount);

        Sanctum::actingAs($other);
        $this->getJson(route('api.v1.account.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('data.available_points', 0);

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.account.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('data.lifetime_redeemed_points', 30);
    }

    public function test_admin_reward_catalog_and_referral_bridge_default_off(): void
    {
        $admin = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.loyalty-rewards.index'))
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.loyalty-rewards.index'))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.loyalty-rewards.store'), [
                'name' => 'Admin fixed',
                'status' => LoyaltyRewardStatus::Active->value,
                'reward_type' => LoyaltyRewardType::FixedOrderDiscount->value,
                'points_cost' => 25,
                'discount_amount' => '15.00',
                'priority' => 1,
                'customer_description' => 'Nice treat',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('loyalty_rewards', [
            'name' => 'Admin fixed',
            'points_cost' => 25,
        ]);

        $this->assertFalse((bool) config('loyalty.referral_bridge.enabled'));
    }

    public function test_existing_earning_still_works(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal' => '45.00',
            'discount_total' => '0.00',
            'taxable_amount' => '45.00',
            'tax_amount' => '0.00',
            'total_amount' => '45.00',
            'completed_at' => now(),
        ]);

        $result = app(LoyaltyServiceInterface::class)->awardForOrder($order);
        $this->assertTrue($result['awarded']);
        $this->assertSame(45, $result['points']);
    }

    protected function creditPoints(User $customer, int $points): void
    {
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => $points,
            'lifetime_earned_points' => $points,
            'lifetime_redeemed_points' => 0,
            'lifetime_adjusted_points' => 0,
        ]);
    }

    protected function placeOrderWithReward(User $customer, ProductVariant $variant, LoyaltyReward $reward): Order
    {
        return app(OrderServiceInterface::class)->store(
            $customer,
            $this->makeTransfer($customer, $variant, $reward),
        );
    }

    protected function makeTransfer(User $customer, ProductVariant $variant, LoyaltyReward $reward): OrderTransfer
    {
        $transfer = new OrderTransfer;
        $transfer->setCustomerId((int) $customer->id);
        $transfer->setCheckoutToken((string) Str::uuid());
        $transfer->setCustomerName($customer->name);
        $transfer->setCustomerEmail($customer->email);
        $transfer->setCustomerPhone($customer->phone ?: '9000000000');
        $transfer->setPickupName($customer->name);
        $transfer->setPickupPhone($customer->phone ?: '9000000000');
        $transfer->setFulfilmentMethod('takeaway');
        $transfer->setPaymentMethod('manual_upi');
        $transfer->setLoyaltyRewardId((int) $reward->id);
        $transfer->setItems([
            ['product_variant_id' => (int) $variant->id, 'quantity' => 1],
        ]);

        return $transfer;
    }

    protected function addVariantToCart(ProductVariant $variant, int $quantity = 1): void
    {
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ])->assertSuccessful();
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
            'preparation_station' => 'bar',
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

    protected function setTaxSettings(bool $enabled, string $percent, bool $inclusive = false): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::TaxEnabled->value],
            ['value' => $enabled ? '1' : '0'],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::TaxPercent->value],
            ['value' => $percent],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::TaxInclusive->value],
            ['value' => $inclusive ? '1' : '0'],
        );
    }
}
