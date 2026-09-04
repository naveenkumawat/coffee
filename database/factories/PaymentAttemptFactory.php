<?php

namespace Database\Factories;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'customer_id' => User::factory()->customer(),
            'provider' => PaymentMethod::Razorpay->apiKey(),
            'provider_payment_id' => null,
            'provider_order_id' => 'order_'.fake()->unique()->bothify('????########'),
            'provider_reference' => null,
            'amount' => '120.00',
            'currency' => 'INR',
            'status' => PaymentAttemptStatus::RequiresAction,
            'initiated_at' => now(),
            'confirmed_at' => null,
            'failed_at' => null,
            'failure_code' => null,
            'failure_message' => null,
            'client_payload' => [],
            'meta' => [],
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentAttemptStatus::Confirmed,
            'provider_payment_id' => 'pay_'.fake()->unique()->bothify('????########'),
            'confirmed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentAttemptStatus::Failed,
            'failed_at' => now(),
            'failure_code' => 'gateway_error',
            'failure_message' => 'Payment failed.',
        ]);
    }
}
