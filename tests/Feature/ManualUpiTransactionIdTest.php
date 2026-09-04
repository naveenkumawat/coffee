<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Order\OrderServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualUpiTransactionIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('broadcasting.default', 'null');
        config()->set('coffee.payments.upi_id', 'cafe@upi');
        config()->set('coffee.payments.methods.manual_upi.enabled', true);
        config()->set('coffee.payments.methods.cash.enabled', true);
        config()->set('coffee.payments.methods.razorpay.enabled', false);
    }

    public function test_submission_does_not_mark_paid_and_verification_does(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->manager()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
            'total_amount' => '18.00',
            'payment_expires_at' => now()->addHour(),
        ]);

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => '  UTR-ABC123456  ',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'awaiting_review')
            ->assertJsonPath('data.payment_transaction_id', 'UTR-ABC123456')
            ->assertJsonPath('data.status', 'pending_payment');

        $this->assertTrue($order->fresh()->canCustomerCancel($customer));

        $this->actingAs($admin, 'admin')
            ->patch(route('administrator.orders.status.update', $order), [
                'status' => OrderStatus::PaymentConfirmed->value,
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->status);
        $this->assertSame(PaymentStatus::Confirmed, $order->payment_status);
        $this->assertFalse($order->canCustomerCancel($customer));
    }

    public function test_duplicate_confirmed_transaction_id_is_rejected(): void
    {
        $customer = User::factory()->customer()->create();
        Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_method' => PaymentMethod::Manual,
            'payment_transaction_id' => 'UTRDUPLICATE999',
            'payment_confirmed_at' => now(),
        ]);

        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => 'UTRDUPLICATE999',
        ])->assertUnprocessable()->assertJsonValidationErrors(['transaction_id']);
    }

    public function test_disabled_manual_upi_rejects_transaction_submission(): void
    {
        config()->set('coffee.payments.methods.manual_upi.enabled', false);
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => 'UTRDISABLED001',
        ])->assertUnprocessable();
    }

    public function test_verification_pending_orders_are_excluded_from_expiry(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::AwaitingReview,
            'payment_method' => PaymentMethod::Manual,
            'payment_transaction_id' => 'UTRPENDINGHOLD1',
            'payment_proof_uploaded_at' => now()->subMinutes(10),
            'payment_expires_at' => now()->subMinute(),
            'placed_at' => now()->subHours(3),
        ]);

        $cancelled = app(OrderServiceInterface::class)->expireDuePendingPaymentOrders();

        $this->assertSame(0, $cancelled);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(PaymentStatus::AwaitingReview, $order->fresh()->payment_status);
    }

    public function test_rejected_transaction_can_be_corrected(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->manager()->create();
        $order = Order::factory()->takeaway()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::AwaitingReview,
            'payment_method' => PaymentMethod::Manual,
            'payment_transaction_id' => 'UTRWRONG001',
            'payment_proof_uploaded_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.orders.payment-proof.reject', $order), [
                'notes' => 'Not found',
            ])->assertRedirect();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => 'UTRCORRECT002',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_transaction_id', 'UTRCORRECT002')
            ->assertJsonPath('data.payment_status', 'awaiting_review');
    }

    public function test_historical_screenshot_still_readable_with_transaction_id_flow(): void
    {
        Storage::fake('local');
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->withPaymentProof()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_method' => PaymentMethod::Manual,
            'payment_transaction_id' => 'UTRWITHOLDSHOT1',
            'payment_status' => PaymentStatus::AwaitingReview,
        ]);
        Storage::disk('local')->put($order->payment_proof_path, 'legacy-bytes');

        Sanctum::actingAs($customer);
        $this->get(route('api.v1.orders.payment-proof.show', $order))->assertOk();
    }
}
