<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('coffee.payments.methods.razorpay.enabled', true);
        config()->set('coffee.payments.gateways.razorpay', [
            'key_id' => 'rzp_test_key',
            'key_secret' => 'rzp_test_secret',
            'webhook_secret' => 'rzp_hook_secret',
            'mode' => 'test',
        ]);
        config()->set('coffee.payments.gateways.payu', [
            'merchant_key' => 'payu_key',
            'merchant_salt' => 'payu_salt',
            'mode' => 'test',
        ]);
        config()->set('coffee.payments.methods.payu.enabled', true);
    }

    public function test_valid_razorpay_webhook_confirms_and_duplicate_is_idempotent(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Razorpay,
            'total_amount' => '12.00',
            'payment_expires_at' => now()->addHour(),
        ]);
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'provider' => 'razorpay',
            'provider_order_id' => 'order_WH',
            'amount' => '12.00',
            'status' => PaymentAttemptStatus::RequiresAction,
        ]);

        $raw = json_encode([
            'id' => 'evt_duplicate',
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_WH',
                        'order_id' => 'order_WH',
                        'amount' => 1200,
                        'currency' => 'INR',
                        'status' => 'captured',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $raw, 'rzp_hook_secret');

        $this->call('POST', route('api.webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Razorpay-Signature' => $signature,
        ], (string) $raw)->assertOk();

        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::Confirmed, $attempt->fresh()->status);
        $this->assertSame(1, PaymentWebhookEvent::query()->count());

        $this->call('POST', route('api.webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Razorpay-Signature' => $signature,
        ], (string) $raw)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(1, PaymentWebhookEvent::query()->count());
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
    }

    public function test_invalid_razorpay_webhook_signature_is_rejected(): void
    {
        $raw = json_encode(['id' => 'evt_bad', 'event' => 'payment.captured'], JSON_UNESCAPED_SLASHES);

        $this->call('POST', route('api.webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Razorpay-Signature' => 'invalid',
        ], (string) $raw)->assertOk();

        $this->assertSame(0, PaymentAttempt::query()->where('status', PaymentAttemptStatus::Confirmed)->count());
    }

    public function test_payu_success_hash_confirms_payment(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::PayU,
            'total_amount' => '15.00',
            'payment_expires_at' => now()->addHour(),
        ]);
        $attempt = PaymentAttempt::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'provider' => 'payu',
            'provider_order_id' => 'PUTXN15',
            'amount' => '15.00',
            'status' => PaymentAttemptStatus::RequiresAction,
        ]);

        $payload = [
            'status' => 'success',
            'txnid' => 'PUTXN15',
            'amount' => '15.00',
            'productinfo' => 'Order',
            'firstname' => 'Test',
            'email' => 'test@example.com',
            'udf1' => (string) $order->id,
            'udf2' => (string) $attempt->id,
            'mihpayid' => 'MIH15',
        ];
        $reverse = implode('|', [
            'payu_salt',
            'success',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $payload['udf2'],
            $payload['udf1'],
            $payload['email'],
            $payload['firstname'],
            $payload['productinfo'],
            $payload['amount'],
            $payload['txnid'],
            'payu_key',
        ]);
        $payload['hash'] = strtolower(hash('sha512', $reverse));

        $this->post(route('api.webhooks.payu'), $payload)->assertOk();

        $this->assertSame(OrderStatus::PaymentConfirmed, $order->fresh()->status);
        $this->assertSame(PaymentAttemptStatus::Confirmed, $attempt->fresh()->status);
    }
}
