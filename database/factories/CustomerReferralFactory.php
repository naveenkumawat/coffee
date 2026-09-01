<?php

namespace Database\Factories;

use App\Enums\ReferralStatus;
use App\Models\CustomerReferral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReferral>
 */
class CustomerReferralFactory extends Factory
{
    protected $model = CustomerReferral::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'referrer_user_id' => User::factory()->customer(),
            'referred_user_id' => User::factory()->customer(),
            'referral_code_snapshot' => strtoupper(fake()->unique()->bothify('???####')),
            'status' => ReferralStatus::Registered,
            'qualified_order_id' => null,
            'qualified_at' => null,
        ];
    }

    public function rewarded(): static
    {
        return $this->state(fn (): array => [
            'status' => ReferralStatus::Rewarded,
            'qualified_at' => now(),
        ]);
    }
}
