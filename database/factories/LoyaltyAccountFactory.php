<?php

namespace Database\Factories;

use App\Models\LoyaltyAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyAccount>
 */
class LoyaltyAccountFactory extends Factory
{
    protected $model = LoyaltyAccount::class;

    public function definition(): array
    {
        return [
            'customer_id' => User::factory()->customer(),
            'available_points' => 0,
            'lifetime_earned_points' => 0,
            'lifetime_redeemed_points' => 0,
            'lifetime_adjusted_points' => 0,
            'version' => 1,
        ];
    }
}
