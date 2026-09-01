<?php

namespace Tests\Feature;

use App\Enums\CustomerRewardStatus;
use App\Enums\CustomerRewardType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\ReferralStatus;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Events\Order\OrderStatusChanged;
use App\Models\CustomerReferral;
use App\Models\CustomerReward;
use App\Models\Order;
use App\Models\OrderRewardRedemption;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Cart\CartServiceInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\Referral\ReferralServiceInterface;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use App\Transfers\Order\OrderTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_attaches_referral_and_assigns_codes(): void
    {
        $this->enableReferrals();
        $referrer = User::factory()->customer()->create();
        $code = app(ReferralServiceInterface::class)->ensureCustomerReferralCode($referrer);

        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Friend One',
            'email' => 'friend1@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => strtolower($code),
        ])->assertCreated();

        $friend = User::query()->where('email', 'friend1@example.com')->firstOrFail();
        $this->assertNotNull($friend->referral_code);
        $this->assertSame((int) $referrer->id, (int) $friend->referred_by_user_id);
        $this->assertDatabaseHas('customer_referrals', [
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $friend->id,
            'status' => ReferralStatus::Registered->value,
        ]);
    }

    public function test_referrer_cannot_be_changed_after_registration(): void
    {
        $this->enableReferrals();
        $referrer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $code = app(ReferralServiceInterface::class)->ensureCustomerReferralCode($referrer);
        app(ReferralServiceInterface::class)->ensureCustomerReferralCode($other);

        $friend = User::factory()->customer()->create();
        app(ReferralServiceInterface::class)->attachReferralOnRegistration($friend, $code);

        $this->expectException(ValidationException::class);
        app(ReferralServiceInterface::class)->attachReferralOnRegistration($friend->fresh(), $other->referral_code);
    }

    public function test_payment_confirmed_qualifies_referral_and_creates_immutable_free_drink_reward(): void
    {
        Notification::fake();
        $this->enableReferrals();
        $variant = $this->makePurchasableVariant('120.00');
        $this->configureFreeDrinkReward($variant);

        $referrer = User::factory()->customer()->create();
        $friend = User::factory()->customer()->create();
        $code = app(ReferralServiceInterface::class)->ensureCustomerReferralCode($referrer);
        app(ReferralServiceInterface::class)->attachReferralOnRegistration($friend, $code);

        $order = Order::factory()->create([
            'customer_id' => $friend->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'total_amount' => '150.00',
        ]);

        $admin = User::factory()->create(['role' => UserRole::Owner]);
        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus(OrderStatus::PaymentConfirmed->value);
        app(OrderServiceInterface::class)->transition($order, $admin, $transfer);

        $referral = CustomerReferral::query()->where('referred_user_id', $friend->id)->firstOrFail();
        $this->assertSame(ReferralStatus::Rewarded, $referral->status);

        $reward = CustomerReward::query()->where('source_referral_id', $referral->id)->firstOrFail();
        $this->assertSame(CustomerRewardType::FreeDrink, $reward->reward_type);
        $this->assertSame((int) $variant->product_id, (int) $reward->product_id);
        $this->assertSame((int) $variant->id, (int) $reward->variant_id);

        // Changing settings must not mutate existing reward snapshots.
        $this->configureFreeDrinkReward($this->makePurchasableVariant('99.00'));
        $reward->refresh();
        $this->assertSame((int) $variant->product_id, (int) $reward->product_id);

        // Idempotent: second PaymentConfirmed event does not create another reward.
        OrderStatusChanged::dispatch($order->fresh(), OrderStatus::PendingPayment, OrderStatus::PaymentConfirmed);
        $this->assertSame(1, CustomerReward::query()->where('user_id', $referrer->id)->count());
    }

    public function test_free_drink_preserves_gst_basis_exclusive(): void
    {
        $this->enableReferrals();
        $this->setTaxSettings(true, '5.00', false);
        $variant = $this->makePurchasableVariant('100.00');
        $this->configureFreeDrinkReward($variant);

        $customer = User::factory()->customer()->create();
        $reward = CustomerReward::factory()->freeDrink($variant->product, $variant->id)->create([
            'user_id' => $customer->id,
        ]);

        Sanctum::actingAs($customer);
        $this->addVariantToCart($variant);
        $this->postJson(route('api.v1.cart.referral-rewards.free-drink'), [
            'reward_id' => $reward->id,
            'fulfilment_method' => 'takeaway',
        ])->assertOk();

        $summary = $this->getJson(route('api.v1.cart.show'))->json('meta.summary');

        $this->assertSame('100.00', $summary['subtotal']);
        $this->assertSame('100.00', $summary['free_drink_benefit']);
        $this->assertSame('0.00', $summary['discount_total']);
        $this->assertSame('100.00', $summary['tax']['taxable_amount']);
        $this->assertSame('5.00', $summary['tax']['amount']);
        $this->assertSame('5.00', $summary['total']);
    }

    public function test_free_drink_preserves_gst_basis_inclusive(): void
    {
        $this->enableReferrals();
        $this->setTaxSettings(true, '5.00', true);
        $variant = $this->makePurchasableVariant('105.00');
        $this->configureFreeDrinkReward($variant);

        $customer = User::factory()->customer()->create();
        $reward = CustomerReward::factory()->freeDrink($variant->product, $variant->id)->create([
            'user_id' => $customer->id,
        ]);

        Sanctum::actingAs($customer);
        $this->addVariantToCart($variant);
        $this->postJson(route('api.v1.cart.referral-rewards.free-drink'), [
            'reward_id' => $reward->id,
        ])->assertOk();

        $summary = $this->getJson(route('api.v1.cart.show'))->json('meta.summary');

        // Inclusive: benefit = 105 - 5 GST component = 100; payable retains GST = 5
        $this->assertSame('100.00', $summary['free_drink_benefit']);
        $this->assertSame('105.00', $summary['tax']['taxable_amount']);
        $this->assertSame('5.00', $summary['tax']['amount']);
        $this->assertSame('5.00', $summary['total']);
    }

    public function test_expired_reward_is_cleared_from_cart(): void
    {
        $this->enableReferrals();
        $variant = $this->makePurchasableVariant('80.00');
        $customer = User::factory()->customer()->create();
        $reward = CustomerReward::factory()->freeDrink($variant->product, $variant->id)->expired()->create([
            'user_id' => $customer->id,
        ]);

        Sanctum::actingAs($customer);
        $this->addVariantToCart($variant);

        $cart = app(CartServiceInterface::class)->getForCustomer($customer);
        $cart->forceFill(['referral_free_drink_reward_id' => $reward->id])->save();

        $summary = app(CartServiceInterface::class)->summarize($cart->fresh(['items.productVariant.product']));

        $this->assertSame('reward_expired', $summary['reward_error']);
        $this->assertNull($cart->fresh()->referral_free_drink_reward_id);
        $this->assertSame(CustomerRewardStatus::Expired, $reward->fresh()->status);
    }

    public function test_only_one_referral_reward_per_order(): void
    {
        $this->enableReferrals();
        $variant = $this->makePurchasableVariant('100.00');
        $customer = User::factory()->customer()->create();
        $freeDrink = CustomerReward::factory()->freeDrink($variant->product, $variant->id)->create([
            'user_id' => $customer->id,
        ]);
        $coupon = CustomerReward::factory()->coupon('REF-ONEONLY')->create([
            'user_id' => $customer->id,
        ]);

        Sanctum::actingAs($customer);
        $this->addVariantToCart($variant);

        $this->postJson(route('api.v1.cart.referral-rewards.free-drink'), [
            'reward_id' => $freeDrink->id,
        ])->assertOk();

        $this->postJson(route('api.v1.cart.referral-rewards.coupon.apply'), [
            'referral_coupon' => 'REF-ONEONLY',
        ])->assertOk();

        $cart = app(CartServiceInterface::class)->getForCustomer($customer);
        $this->assertNull($cart->referral_free_drink_reward_id);
        $this->assertSame((int) $coupon->id, (int) $cart->referral_coupon_reward_id);
    }

    public function test_checkout_redeems_reward_atomically_and_failed_checkout_leaves_reward_available(): void
    {
        $this->enableReferrals();
        $this->setTaxSettings(false, '0.00', false);
        $variant = $this->makePurchasableVariant('100.00');
        $customer = User::factory()->customer()->create(['phone' => '9111222333']);
        $reward = CustomerReward::factory()->freeDrink($variant->product, $variant->id)->create([
            'user_id' => $customer->id,
        ]);

        Sanctum::actingAs($customer);
        $this->addVariantToCart($variant);
        $this->postJson(route('api.v1.cart.referral-rewards.free-drink'), [
            'reward_id' => $reward->id,
        ])->assertOk();

        // Failed checkout (empty/expired token) must not redeem.
        $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => 'invalid-token',
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
        ])->assertStatus(422);

        $this->assertSame(CustomerRewardStatus::Available, $reward->fresh()->status);

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
        ])->assertCreated();

        $this->assertSame(CustomerRewardStatus::Redeemed, $reward->fresh()->status);
        $this->assertDatabaseHas('order_reward_redemptions', [
            'customer_reward_id' => $reward->id,
            'reward_type' => CustomerRewardType::FreeDrink->value,
        ]);
    }

    public function test_concurrent_redemption_allows_only_one_winner(): void
    {
        $this->enableReferrals();
        $this->setTaxSettings(false, '0.00', false);
        $variant = $this->makePurchasableVariant('90.00');
        $customer = User::factory()->customer()->create(['phone' => '9111555666']);
        $reward = CustomerReward::factory()->freeDrink($variant->product, $variant->id)->create([
            'user_id' => $customer->id,
        ]);

        $makeTransfer = function () use ($customer, $variant, $reward): OrderTransfer {
            $transfer = new OrderTransfer;
            $transfer->setCustomerId((int) $customer->id);
            $transfer->setCheckoutToken((string) Str::uuid());
            $transfer->setCustomerName($customer->name);
            $transfer->setCustomerEmail($customer->email);
            $transfer->setCustomerPhone($customer->phone);
            $transfer->setPickupName($customer->name);
            $transfer->setPickupPhone($customer->phone);
            $transfer->setFulfilmentMethod('takeaway');
            $transfer->setPaymentMethod('manual_upi');
            $transfer->setReferralFreeDrinkRewardId((int) $reward->id);
            $transfer->setItems([
                ['product_variant_id' => (int) $variant->id, 'quantity' => 1],
            ]);

            return $transfer;
        };

        $orderService = app(OrderServiceInterface::class);
        $orderService->store($customer, $makeTransfer());

        try {
            $orderService->store($customer, $makeTransfer());
            $this->fail('Expected second redemption to fail.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(1, OrderRewardRedemption::query()->where('customer_reward_id', $reward->id)->count());
        $this->assertSame(CustomerRewardStatus::Redeemed, $reward->fresh()->status);
    }

    public function test_account_referral_summary_and_active_rewards_endpoints(): void
    {
        $this->enableReferrals();
        $customer = User::factory()->customer()->create();
        app(ReferralServiceInterface::class)->ensureCustomerReferralCode($customer);
        CustomerReward::factory()->coupon('REF-ACTIVE')->create(['user_id' => $customer->id]);
        CustomerReward::factory()->coupon('REF-GONE')->expired()->create(['user_id' => $customer->id]);

        Sanctum::actingAs($customer);

        $this->getJson(route('api.v1.customer.referral.show'))
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonStructure(['data' => ['referral_code', 'share_url', 'stats']]);

        $this->getJson(route('api.v1.customer.rewards.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data.rewards');
    }

    protected function enableReferrals(): void
    {
        $defaults = [
            WebsiteSettingKey::ReferralEnabled->value => '1',
            WebsiteSettingKey::ReferralRewardType->value => 'free_drink',
            WebsiteSettingKey::ReferralRewardQuantity->value => '1',
            WebsiteSettingKey::ReferralRewardRedemptionDurationDays->value => '30',
            WebsiteSettingKey::ReferralCouponDiscountType->value => 'fixed',
            WebsiteSettingKey::ReferralCouponDiscountValue->value => '50.00',
        ];

        foreach ($defaults as $key => $value) {
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

    protected function configureFreeDrinkReward(ProductVariant $variant): void
    {
        $map = [
            WebsiteSettingKey::ReferralEnabled->value => '1',
            WebsiteSettingKey::ReferralRewardType->value => 'free_drink',
            WebsiteSettingKey::ReferralRewardProductId->value => (string) $variant->product_id,
            WebsiteSettingKey::ReferralRewardVariantId->value => (string) $variant->id,
            WebsiteSettingKey::ReferralRewardQuantity->value => '1',
            WebsiteSettingKey::ReferralRewardRedemptionDurationDays->value => '30',
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

    protected function setTaxSettings(bool $enabled, string $percent, bool $inclusive = false): void
    {
        $map = [
            WebsiteSettingKey::TaxEnabled->value => $enabled ? '1' : '0',
            WebsiteSettingKey::TaxLabel->value => 'GST',
            WebsiteSettingKey::TaxPercent->value => $percent,
            WebsiteSettingKey::TaxInclusive->value => $inclusive ? '1' : '0',
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
