<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('broadcasting.default', 'null');
        config()->set('coffee.payments.upi_id', 'demo@upi');
        config()->set('coffee.payments.methods.manual_upi.enabled', true);
    }

    public function test_customer_can_submit_transaction_id_for_own_pending_payment_order(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => '312345678901',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'awaiting_review')
            ->assertJsonPath('data.payment_transaction_id', '312345678901')
            ->assertJsonPath('data.status', 'pending_payment');

        $order->refresh();

        $this->assertSame(PaymentStatus::AwaitingReview, $order->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('312345678901', $order->payment_transaction_id);
        $this->assertTrue($order->hasManualPaymentEvidence());
    }

    public function test_payment_transaction_submission_is_blocked_for_other_customers_and_invalid_ids(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $owner->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'payment_method' => PaymentMethod::Manual,
        ]);

        Sanctum::actingAs($other);

        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => '312345678901',
        ])->assertForbidden();

        Sanctum::actingAs($owner);

        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => 'bad id!!',
        ])->assertUnprocessable()->assertJsonValidationErrors(['transaction_id']);
    }

    public function test_transaction_id_cannot_be_submitted_after_payment_confirmation(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_confirmed_at' => now(),
            'payment_method' => PaymentMethod::Manual,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => '312345678901',
        ])->assertForbidden();
    }

    public function test_administrator_can_reject_and_verify_transaction_id(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::AwaitingReview,
            'payment_method' => PaymentMethod::Manual,
            'payment_transaction_id' => 'UTRADMINVERIFY1',
            'payment_proof_uploaded_at' => now(),
            'total_amount' => '25.00',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.orders.payment-proof.reject', $order), [
                'notes' => 'Transaction not found in bank statement.',
            ])
            ->assertRedirect(route('administrator.orders.show', $order));

        $order->refresh();
        $this->assertSame(PaymentStatus::Rejected, $order->payment_status);
        $this->assertSame('Transaction not found in bank statement.', $order->payment_proof_rejection_notes);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.orders.payment-proof.upload', $order), [
            'transaction_id' => 'UTRADMINVERIFY2',
        ])->assertOk();

        $this->actingAs($barista, 'admin')
            ->patch(route('administrator.orders.status.update', $order), [
                'status' => OrderStatus::PaymentConfirmed->value,
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->patch(route('administrator.orders.status.update', $order), [
                'status' => OrderStatus::PaymentConfirmed->value,
                'notes' => 'Verified Transaction ID.',
            ])
            ->assertRedirect(route('administrator.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->status);
        $this->assertSame(PaymentStatus::Confirmed, $order->payment_status);
        $this->assertNotNull($order->payment_confirmed_at);
        $this->assertSame($admin->id, $order->payment_received_by_id);
    }

    public function test_historical_screenshot_remains_readable(): void
    {
        Storage::fake('local');

        $admin = User::factory()->manager()->create();
        $order = Order::factory()->withPaymentProof()->create([
            'status' => OrderStatus::PendingPayment,
            'payment_method' => PaymentMethod::Manual,
            'payment_transaction_id' => 'UTRWITHSCREEN1',
        ]);

        Storage::disk('local')->put($order->payment_proof_path, 'admin-proof-bytes');

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.orders.payment-proof.show', $order))
            ->assertOk();
    }

    public function test_payment_confirmed_at_is_preserved_on_later_status_transitions(): void
    {
        $admin = User::factory()->manager()->create();
        $confirmedAt = now()->subHour();
        $order = Order::factory()->create([
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_method' => PaymentMethod::Manual,
            'payment_transaction_id' => 'UTRKEEPCONFIRM1',
            'payment_confirmed_at' => $confirmedAt,
            'payment_proof_uploaded_at' => now()->subHours(2),
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('administrator.orders.status.update', $order), [
                'status' => OrderStatus::Accepted->value,
            ])
            ->assertRedirect(route('administrator.orders.show', $order));

        $this->assertEquals(
            $confirmedAt->toIso8601String(),
            $order->fresh()->payment_confirmed_at?->toIso8601String(),
        );
    }
}
