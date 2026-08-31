<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_upload_image_proof_for_own_pending_payment_order(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($customer);

        $file = UploadedFile::fake()->image('upi-screenshot.jpg', 640, 480);

        $this->post(route('api.v1.orders.payment-proof.upload', $order), [
            'payment_proof' => $file,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.payment_proof.uploaded', true)
            ->assertJsonPath('data.payment_status', 'awaiting_review');

        $order->refresh();

        $this->assertSame(PaymentStatus::AwaitingReview, $order->payment_status);
        $this->assertTrue($order->hasPaymentProof());
        Storage::disk('local')->assertExists($order->payment_proof_path);
    }

    public function test_payment_proof_upload_is_blocked_for_other_customers_and_non_images(): void
    {
        Storage::fake('local');

        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $owner->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($other);

        $this->post(route('api.v1.orders.payment-proof.upload', $order), [
            'payment_proof' => UploadedFile::fake()->image('stolen.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertForbidden();

        Sanctum::actingAs($owner);

        $this->post(route('api.v1.orders.payment-proof.upload', $order), [
            'payment_proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_proof']);
    }

    public function test_payment_proof_cannot_be_uploaded_after_payment_confirmation(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_confirmed_at' => now(),
        ]);

        Sanctum::actingAs($customer);

        $this->post(route('api.v1.orders.payment-proof.upload', $order), [
            'payment_proof' => UploadedFile::fake()->image('late.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_administrator_can_reject_proof_and_barista_cannot_confirm_payment(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create();
        $admin = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $order = Order::factory()->withPaymentProof()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        Storage::disk('local')->put($order->payment_proof_path, 'fake-image-bytes');

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.orders.payment-proof.reject', $order), [
                'notes' => 'Screenshot is cropped. Please re-upload.',
            ])
            ->assertRedirect(route('administrator.orders.show', $order));

        $order->refresh();
        $this->assertSame(PaymentStatus::Rejected, $order->payment_status);
        $this->assertSame('Screenshot is cropped. Please re-upload.', $order->payment_proof_rejection_notes);

        $this->actingAs($barista, 'admin')
            ->from(route('barista.orders.show', $order))
            ->patch(route('barista.orders.status.update', $order), [
                'status' => OrderStatus::PaymentConfirmed->value,
            ])
            ->assertRedirect(route('barista.orders.show', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);

        $this->actingAs($admin, 'admin')
            ->patch(route('administrator.orders.status.update', $order), [
                'status' => OrderStatus::PaymentConfirmed->value,
                'notes' => 'Verified replacement screenshot.',
            ])
            ->assertRedirect(route('administrator.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->status);
        $this->assertSame(PaymentStatus::Confirmed, $order->payment_status);
        $this->assertNotNull($order->payment_confirmed_at);
    }

    public function test_administrator_can_view_payment_proof_stream(): void
    {
        Storage::fake('local');

        $admin = User::factory()->manager()->create();
        $order = Order::factory()->withPaymentProof()->create([
            'status' => OrderStatus::PendingPayment,
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
        $order = Order::factory()->paymentConfirmed()->create([
            'payment_confirmed_at' => $confirmedAt,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('administrator.orders.status.update', $order), [
                'status' => OrderStatus::Accepted->value,
                'notes' => 'Accepted after payment.',
            ])
            ->assertRedirect(route('administrator.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Accepted, $order->status);
        $this->assertSame(
            $confirmedAt->format('Y-m-d H:i:s'),
            $order->payment_confirmed_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_customer_can_view_own_payment_proof_stream(): void
    {
        Storage::fake('local');

        $customer = User::factory()->customer()->create();
        $order = Order::factory()->withPaymentProof()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
        ]);

        Storage::disk('local')->put($order->payment_proof_path, 'proof-bytes');

        Sanctum::actingAs($customer);

        $this->get(route('api.v1.orders.payment-proof.show', $order))
            ->assertOk();
    }
}
