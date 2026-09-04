<?php

namespace Database\Factories;

use App\Enums\LoyaltyTransactionSourceType;
use App\Enums\LoyaltyTransactionType;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoyaltyPointTransaction>
 */
class LoyaltyPointTransactionFactory extends Factory
{
    protected $model = LoyaltyPointTransaction::class;

    public function definition(): array
    {
        return [
            'loyalty_account_id' => LoyaltyAccount::factory(),
            'customer_id' => fn (array $attrs) => LoyaltyAccount::query()->find($attrs['loyalty_account_id'])?->customer_id,
            'type' => LoyaltyTransactionType::Earn,
            'points' => 10,
            'source_type' => LoyaltyTransactionSourceType::Order,
            'source_id' => null,
            'idempotency_key' => 'test:'.Str::lower(Str::random(16)),
            'reason_code' => 'order_earn',
            'description' => 'Test earn',
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
