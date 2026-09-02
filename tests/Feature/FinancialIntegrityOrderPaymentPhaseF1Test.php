<?php

namespace Tests\Feature;

use App\Enums\CustomerRewardStatus;
use App\Enums\CustomerRewardType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReferralStatus;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Models\CustomerReferral;
use App\Models\CustomerReward;
use App\Models\Order;
use App\Models\OrderRewardRedemption;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Order\OrderServiceInterface;
use App\Services\Referral\ReferralServiceInterface;
use App\Services\Tax\TaxCalculatorInterface;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialIntegrityOrderPaymentPhaseF1Test extends TestCase
{
    use RefreshDatabase;

    public function test_cash_accepted_then_cash_received_qualifies_referral_once(): void
    {
        $this->enableReferralsAsCoupon();

        $referrer = User::factory()->customer()->create();
        app(ReferralServiceInterface::class)->ensureCustomerReferralCode($referrer);
        $friend = User::factory()->customer()->create();
        CustomerReferral::query()->create([
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $friend->id,
            'referral_code_snapshot' => $referrer->referral_code,
            'status' => ReferralStatus::Registered,
        ]);

        $operator = User::factory()->create(['role' => UserRole::Operator, 'is_active' => true]);
        $order = Order::factory()->cash()->create([
            'customer_id' => $friend->id,
            'status' => OrderStatus::Accepted,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Cash,
            'total_amount' => '100.00',
            'payment_confirmed_at' => null,
        ]);

        app(OrderServiceInterface::class)->markCashReceived($order, $operator);

        $this->assertSame(1, CustomerReward::query()->where('user_id', $referrer->id)->count());

        try {
            app(OrderServiceInterface::class)->markCashReceived($order->fresh(), $operator);
            $this->fail('Expected duplicate cash receive to fail.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(1, CustomerReward::query()->where('user_id', $referrer->id)->count());
    }

    public function test_unpaid_cancelled_order_restores_unexpired_reward(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->manager()->create();
        $reward = CustomerReward::factory()->coupon('SAVE-ME')->create([
            'user_id' => $customer->id,
            'status' => CustomerRewardStatus::Redeemed,
            'expires_at' => now()->addDays(10),
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
        ]);

        $reward->forceFill(['redeemed_order_id' => $order->id, 'redeemed_at' => now()])->save();
        OrderRewardRedemption::query()->create([
            'order_id' => $order->id,
            'customer_reward_id' => $reward->id,
            'reward_type' => CustomerRewardType::Coupon,
            'description_snapshot' => 'Coupon',
            'benefit_amount' => '5.00',
            'coupon_code_snapshot' => 'SAVE-ME',
        ]);

        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus(OrderStatus::Cancelled->value);
        $transfer->setNotes('Customer cancelled');

        app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $transfer,
        );

        $this->assertSame(CustomerRewardStatus::Available, $reward->fresh()->status);
        $this->assertNull($reward->fresh()->redeemed_order_id);
    }

    public function test_expired_reward_not_restored_to_available(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->manager()->create();
        $reward = CustomerReward::factory()->coupon('OLD')->create([
            'user_id' => $customer->id,
            'status' => CustomerRewardStatus::Redeemed,
            'expires_at' => now()->subDay(),
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $reward->forceFill(['redeemed_order_id' => $order->id, 'redeemed_at' => now()])->save();
        OrderRewardRedemption::query()->create([
            'order_id' => $order->id,
            'customer_reward_id' => $reward->id,
            'reward_type' => CustomerRewardType::Coupon,
            'description_snapshot' => 'Coupon',
            'benefit_amount' => '5.00',
            'coupon_code_snapshot' => 'OLD',
        ]);

        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus(OrderStatus::Cancelled->value);
        $transfer->setNotes('Cancelled');

        app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $transfer,
        );

        $this->assertSame(CustomerRewardStatus::Expired, $reward->fresh()->status);
    }

    public function test_paid_cancelled_order_does_not_auto_restore_reward(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->manager()->create();
        $reward = CustomerReward::factory()->coupon('PAID')->create([
            'user_id' => $customer->id,
            'status' => CustomerRewardStatus::Redeemed,
            'expires_at' => now()->addDays(10),
        ]);

        $order = Order::factory()->paymentConfirmed()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Accepted,
            'payment_status' => PaymentStatus::Confirmed,
        ]);

        $reward->forceFill(['redeemed_order_id' => $order->id, 'redeemed_at' => now()])->save();
        OrderRewardRedemption::query()->create([
            'order_id' => $order->id,
            'customer_reward_id' => $reward->id,
            'reward_type' => CustomerRewardType::Coupon,
            'description_snapshot' => 'Coupon',
            'benefit_amount' => '5.00',
            'coupon_code_snapshot' => 'PAID',
        ]);

        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus(OrderStatus::Cancelled->value);
        $transfer->setNotes('After payment');

        app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $transfer,
        );

        $this->assertSame(CustomerRewardStatus::Redeemed, $reward->fresh()->status);
        $this->assertSame((int) $order->id, (int) $reward->fresh()->redeemed_order_id);
    }

    public function test_proofless_upi_confirmation_is_rejected(): void
    {
        $admin = User::factory()->manager()->create();
        $order = Order::factory()->create([
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
            'payment_proof_path' => null,
        ]);

        try {
            $transfer = new OrderStatusTransitionTransfer;
            $transfer->setStatus(OrderStatus::PaymentConfirmed->value);
            $transfer->setNotes('No proof');

            app(OrderServiceInterface::class)->transition(
                $order,
                $admin,
                $transfer,
            );
            $this->fail('Expected proofless UPI confirmation to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_concurrent_cash_received_second_call_fails(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator, 'is_active' => true]);
        $order = Order::factory()->cash()->create([
            'status' => OrderStatus::Accepted,
            'payment_status' => PaymentStatus::Pending,
            'payment_confirmed_at' => null,
        ]);

        $service = app(OrderServiceInterface::class);
        $service->markCashReceived($order, $operator);

        try {
            $service->markCashReceived($order->fresh(), $operator);
            $this->fail('Expected second cash receive to fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment', $e->errors());
        }
    }

    public function test_free_drink_order_snapshot_cafe_total_matches_order_total(): void
    {
        $this->setTax(enabled: true, percent: '5.00', inclusive: false);

        $order = Order::factory()->create([
            'subtotal' => '200.00',
            'discount_total' => '0.00',
            'tax_enabled_snapshot' => true,
            'tax_label_snapshot' => 'GST',
            'tax_percent_snapshot' => '5.00',
            'tax_inclusive_snapshot' => false,
            // Free drink: GST basis 200, payable merchandise 100 → tax 10, total 110
            'taxable_amount' => '200.00',
            'tax_amount' => '10.00',
            'total_amount' => '110.00',
        ]);

        $fromSnapshot = app(TaxCalculatorInterface::class)->fromOrderSnapshot($order);

        $this->assertSame('110.00', $fromSnapshot->cafeTotal);
        $this->assertSame('200.00', $fromSnapshot->taxableAmount);
        $this->assertSame('10.00', $fromSnapshot->taxAmount);
    }

    protected function enableReferralsAsCoupon(): void
    {
        $this->putSetting(WebsiteSettingKey::ReferralEnabled, '1');
        $this->putSetting(WebsiteSettingKey::ReferralRewardType, 'coupon');
        $this->putSetting(WebsiteSettingKey::ReferralCouponDiscountType, 'fixed');
        $this->putSetting(WebsiteSettingKey::ReferralCouponDiscountValue, '10.00');
        $this->putSetting(WebsiteSettingKey::ReferralRewardRedemptionDurationDays, '30');
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
}
