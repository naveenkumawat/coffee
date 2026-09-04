<?php

namespace Database\Seeders;

use App\Enums\LoyaltyRewardStatus;
use App\Enums\LoyaltyRewardType;
use App\Enums\UserRole;
use App\Models\AddOn;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyReward;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Local/testing loyalty rewards + representative customer balances.
 * Uses LoyaltyService adjustments (never manually corrupts balances).
 */
class DemoLoyaltySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoLoyaltySeeder refused: demo loyalty data must never be seeded outside local/testing (APP_ENV='.app()->environment().').',
            );
        }

        Config::set('loyalty.enabled', true);
        Config::set('loyalty.redemption.enabled', true);

        $admin = User::query()->where('email', 'admin@coffee.local')->first()
            ?? User::query()->whereIn('role', [UserRole::Owner->value, UserRole::Manager->value])->first();

        if ($admin === null) {
            throw new RuntimeException('DemoLoyaltySeeder requires an owner/manager user.');
        }

        $espresso = Product::query()->where('name', 'Espresso')->first();
        $hotCoffee = ProductCategory::query()->where('slug', 'hot-coffee')->first();
        $vanillaAddon = AddOn::query()->where('name', 'like', '%Vanilla%')->first()
            ?? AddOn::query()->where('is_active', true)->first();

        $this->upsertReward([
            'name' => '[Demo] ₹50 Reward',
            'status' => LoyaltyRewardStatus::Active,
            'reward_type' => LoyaltyRewardType::FixedOrderDiscount,
            'points_cost' => 100,
            'config' => ['discount_amount' => '50.00'],
            'priority' => 10,
            'customer_description' => 'Example: Fixed order discount for 100 points.',
            'internal_note' => 'Demo fixed_order_discount.',
        ]);

        $this->upsertReward([
            'name' => '[Demo] 10% Loyalty Discount',
            'status' => LoyaltyRewardStatus::Active,
            'reward_type' => LoyaltyRewardType::PercentageOrderDiscount,
            'points_cost' => 150,
            'config' => [
                'percent' => '10.0000',
                'maximum_discount_amount' => '80.00',
            ],
            'minimum_spend' => '100.00',
            'priority' => 20,
            'customer_description' => 'Example: Percentage loyalty discount with cap.',
            'internal_note' => 'Demo percentage_order_discount.',
        ]);

        if ($espresso !== null) {
            $reward = $this->upsertReward([
                'name' => '[Demo] Free Espresso',
                'status' => LoyaltyRewardStatus::Active,
                'reward_type' => LoyaltyRewardType::FreeBaseProduct,
                'points_cost' => 120,
                'config' => [],
                'minimum_spend' => '50.00',
                'priority' => 30,
                'customer_description' => 'Example: Free base product reward.',
                'internal_note' => 'Demo free_base_product.',
            ]);
            $reward->products()->sync([(int) $espresso->getKey()]);
            $reward->productCategories()->sync([]);
            $reward->addOns()->sync([]);
        }

        if ($vanillaAddon !== null) {
            $reward = $this->upsertReward([
                'name' => '[Demo] Free Vanilla Add-on',
                'status' => LoyaltyRewardStatus::Active,
                'reward_type' => LoyaltyRewardType::FreeAddOn,
                'points_cost' => 40,
                'config' => [],
                'priority' => 40,
                'customer_description' => 'Example: Free add-on reward.',
                'internal_note' => 'Demo free_add_on.',
            ]);
            $reward->addOns()->sync([(int) $vanillaAddon->getKey()]);
            $reward->products()->sync([]);
            $reward->productCategories()->sync([]);
        }

        if ($espresso !== null) {
            $reward = $this->upsertReward([
                'name' => '[Demo] Specific Product Reward',
                'status' => LoyaltyRewardStatus::Active,
                'reward_type' => LoyaltyRewardType::SpecificProductReward,
                'points_cost' => 90,
                'config' => [],
                'priority' => 35,
                'customer_description' => 'Example: Product-specific loyalty reward.',
                'internal_note' => 'Demo specific_product_reward.',
            ]);
            $reward->products()->sync([(int) $espresso->getKey()]);
            $reward->productCategories()->sync([]);
            $reward->addOns()->sync([]);
        }

        if ($hotCoffee !== null) {
            $reward = $this->upsertReward([
                'name' => '[Demo] Any Hot Coffee Reward',
                'status' => LoyaltyRewardStatus::Active,
                'reward_type' => LoyaltyRewardType::CategoryProductReward,
                'points_cost' => 110,
                'config' => [],
                'priority' => 36,
                'customer_description' => 'Example: Category-specific hot coffee reward.',
                'internal_note' => 'Demo category_product_reward.',
            ]);
            $reward->productCategories()->sync([(int) $hotCoffee->getKey()]);
            $reward->products()->sync([]);
            $reward->addOns()->sync([]);
        }

        $this->upsertReward([
            'name' => '[Demo] Paused Loyalty Reward',
            'status' => LoyaltyRewardStatus::Paused,
            'reward_type' => LoyaltyRewardType::FixedOrderDiscount,
            'points_cost' => 80,
            'config' => ['discount_amount' => '25.00'],
            'priority' => 5,
            'customer_description' => 'Example: Paused reward (unavailable).',
            'internal_note' => 'Demo paused status.',
        ]);

        $this->upsertReward([
            'name' => '[Demo] Archived Loyalty Reward',
            'status' => LoyaltyRewardStatus::Archived,
            'reward_type' => LoyaltyRewardType::FixedOrderDiscount,
            'points_cost' => 60,
            'config' => ['discount_amount' => '15.00'],
            'priority' => 1,
            'customer_description' => 'Example: Archived reward.',
            'internal_note' => 'Demo archived status.',
        ]);

        $this->upsertReward([
            'name' => '[Demo] High Threshold ₹100',
            'status' => LoyaltyRewardStatus::Active,
            'reward_type' => LoyaltyRewardType::FixedOrderDiscount,
            'points_cost' => 500,
            'config' => ['discount_amount' => '100.00'],
            'priority' => 50,
            'customer_description' => 'Example: Higher points threshold reward.',
            'internal_note' => 'Demo high points_cost.',
        ]);

        $loyalty = app(LoyaltyService::class);

        $states = [
            [
                'email' => 'demo.loyalty.zero@coffee.local',
                'name' => '[Demo] Loyalty Zero Points',
                'phone' => '9100001001',
                'points' => 0,
            ],
            [
                'email' => 'demo.loyalty.low@coffee.local',
                'name' => '[Demo] Loyalty Low Balance',
                'phone' => '9100001002',
                'points' => 35,
            ],
            [
                'email' => 'demo.loyalty.first-reward@coffee.local',
                'name' => '[Demo] Loyalty Enough For First Reward',
                'phone' => '9100001003',
                'points' => 100,
            ],
            [
                'email' => 'demo.loyalty.rich@coffee.local',
                'name' => '[Demo] Loyalty Rich Balance',
                'phone' => '9100001004',
                'points' => 650,
            ],
            [
                'email' => 'demo.loyalty.near@coffee.local',
                'name' => '[Demo] Loyalty Near Reward',
                'phone' => '9100001005',
                'points' => 85,
            ],
            [
                'email' => 'demo.loyalty.debt@coffee.local',
                'name' => '[Demo] Loyalty Debt Customer',
                'phone' => '9100001006',
                'points' => -40,
            ],
            [
                'email' => 'demo.loyalty.earned@coffee.local',
                'name' => '[Demo] Loyalty Recently Earned',
                'phone' => '9100001007',
                'points' => 140,
            ],
            [
                'email' => 'demo.loyalty.redeemed@coffee.local',
                'name' => '[Demo] Loyalty Recently Redeemed',
                'phone' => '9100001008',
                'points' => 60,
            ],
            [
                'email' => 'demo.highvalue@coffee.local',
                'name' => '[Demo] High Value Customer',
                'phone' => '9100001009',
                'points' => 220,
            ],
            [
                'email' => 'demo.coffee.lover@coffee.local',
                'name' => '[Demo] Coffee Affinity Customer',
                'phone' => '9100001010',
                'points' => 130,
            ],
        ];

        foreach ($states as $state) {
            $customer = $this->ensureCustomer($state);
            $loyalty->ensureAccount($customer);
            $account = LoyaltyAccount::query()->where('customer_id', $customer->getKey())->first();
            $current = (int) ($account?->available_points ?? 0);
            $delta = (int) $state['points'] - $current;

            if ($delta !== 0) {
                $loyalty->adjustPoints(
                    $customer,
                    $admin,
                    $delta,
                    'DemoSeeder balance sync for '.$state['email'],
                    sprintf(
                        'demo:loyalty:sync:%s:%d:%d',
                        $state['email'],
                        $current,
                        (int) $state['points'],
                    ),
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertReward(array $attributes): LoyaltyReward
    {
        $reward = LoyaltyReward::query()->withTrashed()->firstOrNew([
            'name' => $attributes['name'],
        ]);
        $reward->fill($attributes);
        $reward->deleted_at = null;
        $reward->save();

        return $reward;
    }

    /**
     * @param  array{email: string, name: string, phone: string, points: int}  $state
     */
    protected function ensureCustomer(array $state): User
    {
        $user = User::query()->firstOrNew(['email' => $state['email']]);
        $user->fill([
            'name' => $state['name'],
            'phone' => $state['phone'],
            'role' => UserRole::Customer,
            'is_active' => true,
            'password' => $user->password ?: Hash::make('password'),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);
        $user->save();

        return $user;
    }
}
