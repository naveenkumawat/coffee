<?php

namespace Database\Factories;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        // Keep unpaid Pending Payment factories inside the live payment window so
        // abuse/limit tests are not flaky against pending-payment expiry rules.
        $placedAt = now()->subMinutes(fake()->numberBetween(1, 30));
        $expiryMinutes = max(1, (int) config('coffee.orders.pending_payment_expiry_minutes', 120));

        return [
            'order_number' => 'CC-'.$placedAt->format('dmy').'-'.str_pad((string) fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'order_date' => $placedAt->format('Y-m-d'),
            'daily_sequence' => fake()->numberBetween(1, 9999),
            'customer_id' => User::factory()->customer(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'pickup_name' => fake()->name(),
            'pickup_phone' => fake()->phoneNumber(),
            'assigned_barista_id' => null,
            'checkout_token' => fake()->optional()->sha1(),
            'status' => OrderStatus::PendingPayment,
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'payment_method' => PaymentMethod::Manual,
            'payment_status' => PaymentStatus::Pending,
            'subtotal' => '120.00',
            'discount_total' => '0.00',
            'tax_enabled_snapshot' => false,
            'tax_label_snapshot' => null,
            'tax_percent_snapshot' => null,
            'tax_inclusive_snapshot' => false,
            'taxable_amount' => '120.00',
            'tax_amount' => '0.00',
            'total_amount' => '120.00',
            'customer_notes' => fake()->optional()->sentence(),
            'pickup_notes' => fake()->optional()->sentence(),
            'placed_at' => $placedAt,
            'payment_expires_at' => $placedAt->copy()->addMinutes($expiryMinutes),
            'payment_confirmed_at' => null,
            'accepted_at' => null,
            'preparing_at' => null,
            'ready_for_pickup_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'rejected_at' => null,
        ];
    }

    public function takeaway(): static
    {
        return $this->state(fn (): array => [
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'delivery_address' => null,
            'delivery_phone' => null,
            'delivery_contact_name' => null,
            'delivery_notes' => null,
            'delivery_provider' => null,
            'delivery_fee_amount' => null,
            'delivery_tracking_reference' => null,
            'delivery_status' => null,
        ]);
    }

    public function delivery(): static
    {
        return $this->state(fn (): array => [
            'fulfilment_method' => OrderFulfilmentMethod::Delivery,
            'pickup_name' => null,
            'pickup_phone' => null,
            'pickup_notes' => null,
            'delivery_address' => fake()->address(),
            'delivery_phone' => fake()->numerify('9#########'),
            'delivery_contact_name' => fake()->name(),
            'delivery_notes' => fake()->optional()->sentence(),
            'delivery_fee_amount' => null,
            'delivery_status' => null,
            'cafe_table_id' => null,
            'table_name_snapshot' => null,
        ]);
    }

    public function dineIn(?CafeTable $table = null): static
    {
        return $this->state(function () use ($table): array {
            $resolved = $table ?? CafeTable::factory()->create();

            return [
                'fulfilment_method' => OrderFulfilmentMethod::DineIn,
                'pickup_name' => null,
                'pickup_phone' => null,
                'pickup_notes' => null,
                'delivery_address' => null,
                'delivery_phone' => null,
                'delivery_contact_name' => null,
                'delivery_notes' => null,
                'cafe_table_id' => $resolved->getKey(),
                'table_name_snapshot' => $resolved->snapshotLabel(),
            ];
        });
    }

    public function withPaymentProof(): static
    {
        return $this->state(fn (): array => [
            'payment_status' => PaymentStatus::AwaitingReview,
            'payment_proof_path' => 'payment-proofs/demo/proof.jpg',
            'payment_proof_disk' => 'local',
            'payment_proof_mime' => 'image/jpeg',
            'payment_proof_size' => 1024,
            'payment_proof_uploaded_at' => now(),
        ]);
    }

    public function paymentConfirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
            'payment_confirmed_at' => now()->subMinutes(5),
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'payment_method' => PaymentMethod::Cash,
            'payment_status' => PaymentStatus::Pending,
            'payment_proof_path' => null,
            'payment_proof_disk' => null,
            'payment_proof_mime' => null,
            'payment_proof_size' => null,
            'payment_proof_uploaded_at' => null,
            'payment_proof_rejection_notes' => null,
        ]);
    }
}
