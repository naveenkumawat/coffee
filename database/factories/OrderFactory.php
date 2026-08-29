<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
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
        $placedAt = fake()->dateTimeBetween('-7 days');

        return [
            'order_number' => 'CC-'.$placedAt->format('dmy').'-'.str_pad((string) fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'order_date' => $placedAt->format('Y-m-d'),
            'daily_sequence' => fake()->numberBetween(1, 9999),
            'customer_id' => User::factory()->customer(),
            'assigned_barista_id' => null,
            'status' => OrderStatus::PendingPayment,
            'subtotal' => '120.00',
            'discount_total' => '0.00',
            'total_amount' => '120.00',
            'customer_notes' => fake()->optional()->sentence(),
            'placed_at' => $placedAt,
            'payment_confirmed_at' => null,
            'accepted_at' => null,
            'preparing_at' => null,
            'ready_for_pickup_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'rejected_at' => null,
        ];
    }
}
