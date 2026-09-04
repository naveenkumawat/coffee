<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnlinePaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('coffee.payments.upi_id', 'demo@upi');
        config()->set('coffee.payments.methods.manual_upi.enabled', true);
        config()->set('coffee.payments.methods.cash.enabled', true);
        config()->set('coffee.payments.methods.razorpay.enabled', true);
        config()->set('coffee.payments.gateways.razorpay', [
            'key_id' => 'rzp_test_key',
            'key_secret' => 'rzp_test_secret',
            'webhook_secret' => 'rzp_hook_secret',
            'mode' => 'test',
        ]);
    }

    public function test_razorpay_initiation_creates_attempt_with_server_amount(): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_RZP123',
                'amount' => 800,
                'currency' => 'INR',
            ], 200),
        ]);

        $customer = User::factory()->customer()->create(['phone' => '9222000001']);
        $order = $this->pendingOrder($customer, '8.00');

        Sanctum::actingAs($customer);
        $response = $this->postJson(route('api.v1.orders.payment.initiate', $order), [
            'payment_method' => 'razorpay',
        ])->assertCreated();

        $this->assertSame('order_RZP123', $response->json('data.client.order_id'));
        $this->assertDatabaseHas('payment_attempts', [
            'order_id' => $order->id,
            'provider' => 'razorpay',
            'provider_order_id' => 'order_RZP123',
            'amount' => '8.00',
            'status' => PaymentAttemptStatus::RequiresAction->value,
        ]);
        $this->assertSame(PaymentMethod::Razorpay, $order->fresh()->payment_method);
    }

    public function test_razorpay_signature_verification_confirms_payment(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->pendingOrder($customer, '10.00');
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'provider' => 'razorpay',
            'provider_order_id' => 'order_ABC',
            'amount' => '10.00',
            'status' => PaymentAttemptStatus::RequiresAction,
        ]);

        $paymentId = 'pay_ABC';
        $signature = hash_hmac('sha256', 'order_ABC|'.$paymentId, 'rzp_test_secret');

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.payment-attempts.verify-return', $attempt), [
            'razorpay_order_id' => 'order_ABC',
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Confirmed, $order->fresh()->payment_status);
        $this->assertFalse($order->fresh()->canCustomerCancel($customer));
    }

    public function test_invalid_signature_and_amount_mismatch_are_rejected(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->pendingOrder($customer, '10.00');
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'provider' => 'razorpay',
            'provider_order_id' => 'order_ABC',
            'amount' => '10.00',
            'status' => PaymentAttemptStatus::RequiresAction,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.payment-attempts.verify-return', $attempt), [
            'razorpay_order_id' => 'order_ABC',
            'razorpay_payment_id' => 'pay_ABC',
            'razorpay_signature' => 'bad',
        ])->assertOk()->assertJsonPath('data.status', 'failed');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);

        $attempt2 = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'provider' => 'razorpay',
            'provider_order_id' => 'order_DEF',
            'amount' => '10.00',
            'status' => PaymentAttemptStatus::RequiresAction,
        ]);

        // Force amount mismatch via webhook path
        $raw = json_encode([
            'id' => 'evt_1',
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_MISMATCH',
                        'order_id' => 'order_DEF',
                        'amount' => 99900,
                        'currency' => 'INR',
                        'status' => 'captured',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac('sha256', (string) $raw, 'rzp_hook_secret');
        $this->call(
            'POST',
            route('api.webhooks.razorpay'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Razorpay-Signature' => $signature,
            ],
            (string) $raw,
        )->assertOk();

        $this->assertSame(PaymentAttemptStatus::Failed, $attempt2->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
    }

    public function test_payu_paytm_phonepe_initiation_uses_server_amount(): void
    {
        Http::fake([
            'securegw-stage.paytm.in/*' => Http::response(['body' => ['txnToken' => 'TOKEN123']], 200),
            'api-preprod.phonepe.com/*' => Http::response([
                'data' => ['instrumentResponse' => ['redirectInfo' => ['url' => 'https://phonepe.test/pay']]],
            ], 200),
        ]);

        config()->set('coffee.payments.methods.payu.enabled', true);
        config()->set('coffee.payments.gateways.payu', [
            'merchant_key' => 'payu_key',
            'merchant_salt' => 'payu_salt',
            'mode' => 'test',
        ]);
        config()->set('coffee.payments.methods.paytm.enabled', true);
        config()->set('coffee.payments.gateways.paytm', [
            'merchant_id' => 'mid',
            'merchant_key' => 'mkey',
            'mode' => 'test',
        ]);
        config()->set('coffee.payments.methods.phonepe.enabled', true);
        config()->set('coffee.payments.gateways.phonepe', [
            'merchant_id' => 'ppmid',
            'salt_key' => 'ppsalt',
            'salt_index' => '1',
            'mode' => 'test',
        ]);

        $customer = User::factory()->customer()->create(['phone' => '9222000003']);
        Sanctum::actingAs($customer);

        $payuOrder = $this->pendingOrder($customer, '9.00');
        $payu = $this->postJson(route('api.v1.orders.payment.initiate', $payuOrder), [
            'payment_method' => 'payu',
        ])->assertCreated();
        $this->assertSame('9.00', (string) $payu->json('data.client.fields.amount'));
        $this->assertArrayNotHasKey('merchant_salt', $payu->json('data.client.fields'));

        $paytmOrder = $this->pendingOrder($customer, '11.00');
        $paytm = $this->postJson(route('api.v1.orders.payment.initiate', $paytmOrder), [
            'payment_method' => 'paytm',
        ])->assertCreated();
        $this->assertSame('11.00', (string) $paytm->json('data.client.amount'));
        $this->assertArrayNotHasKey('merchant_key', $paytm->json('data.client'));

        $phonepeOrder = $this->pendingOrder($customer, '13.00');
        $phonepe = $this->postJson(route('api.v1.orders.payment.initiate', $phonepeOrder), [
            'payment_method' => 'phonepe',
        ])->assertCreated();
        $this->assertSame('13.00', (string) $phonepe->json('data.client.amount'));
        $this->assertNotEmpty($phonepe->json('data.client.redirect_url'));
    }

    public function test_expired_order_cannot_initiate_and_failed_attempt_leaves_order_payable(): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::sequence()
                ->push(['message' => 'fail'], 500)
                ->push(['id' => 'order_RETRY', 'amount' => 700, 'currency' => 'INR'], 200),
        ]);

        $customer = User::factory()->customer()->create(['phone' => '9222000002']);
        $expired = $this->pendingOrder($customer, '7.00');
        $expired->forceFill([
            'payment_expires_at' => now()->subMinute(),
            'placed_at' => now()->subHours(3),
        ])->save();

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.orders.payment.initiate', $expired), [
            'payment_method' => 'razorpay',
        ])->assertUnprocessable();

        $live = $this->pendingOrder($customer, '7.00');
        $this->postJson(route('api.v1.orders.payment.initiate', $live), [
            'payment_method' => 'razorpay',
        ])->assertUnprocessable();

        $this->assertSame(OrderStatus::PendingPayment, $live->fresh()->status);
        $this->assertSame(1, PaymentAttempt::query()->where('order_id', $live->id)->where('status', PaymentAttemptStatus::Failed)->count());

        $this->postJson(route('api.v1.orders.payment.initiate', $live), [
            'payment_method' => 'razorpay',
        ])->assertCreated();
    }

    protected function pendingOrder(User $customer, string $total): Order
    {
        return Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
            'total_amount' => $total,
            'subtotal' => $total,
            'payment_expires_at' => now()->addHour(),
        ]);
    }
}
