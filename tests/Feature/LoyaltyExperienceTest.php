<?php

namespace Tests\Feature;

use App\Enums\LoyaltyRewardType;
use App\Enums\LoyaltyTransactionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Loyalty\LoyaltyRewardServiceInterface;
use App\Services\Loyalty\LoyaltyServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('loyalty.enabled', true);
        config()->set('loyalty.effective_at', null);
        config()->set('loyalty.redemption.enabled', true);
    }

    public function test_zero_point_customer_hub_payload_and_progress(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyReward::factory()->fixed('20.00')->create([
            'name' => 'Free treat',
            'points_cost' => 100,
        ]);

        Sanctum::actingAs($customer);

        $response = $this->getJson(route('api.v1.account.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('data.display_available_points', 0)
            ->assertJsonPath('data.has_points_debt', false)
            ->assertJsonMissingPath('data.recent_transactions.0.idempotency_key')
            ->assertJsonMissingPath('data.recent_transactions.0.metadata');

        $next = $response->json('data.next_reward');
        $this->assertSame('progress', $next['state']);
        $this->assertSame(100, $next['points_needed']);
        $this->assertSame(0, $next['progress_percent']);
        $this->assertStringContainsString('more points to unlock', $next['message']);
        $this->assertNotEmpty($response->json('data.locked'));
        $this->assertSame([], $response->json('data.available_now'));
    }

    public function test_available_and_insufficient_points_messages(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 80,
        ]);
        $affordable = LoyaltyReward::factory()->fixed('15.00')->create([
            'name' => 'Small save',
            'points_cost' => 50,
        ]);
        LoyaltyReward::factory()->fixed('40.00')->create([
            'name' => 'Big save',
            'points_cost' => 200,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $this->makePurchasableVariant('100.00')->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $rewards = collect($this->getJson(route('api.v1.account.loyalty.rewards'))
            ->assertOk()
            ->json('data.rewards'));

        $ready = $rewards->firstWhere('id', $affordable->id);
        $this->assertTrue($ready['eligible']);
        $this->assertSame('available', $ready['state']);
        $this->assertNotEmpty($ready['benefit_label']);

        $locked = $rewards->firstWhere('points_cost', 200);
        $this->assertFalse($locked['eligible']);
        $this->assertSame('120 more points needed', $locked['unavailable_message']);
        $this->assertArrayNotHasKey('config', $locked);
    }

    public function test_next_reward_prefers_lowest_reachable_cost_deterministically(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 40,
        ]);
        LoyaltyReward::factory()->fixed('10.00')->create(['name' => 'Later', 'points_cost' => 120, 'priority' => 10]);
        $near = LoyaltyReward::factory()->fixed('10.00')->create(['name' => 'Near', 'points_cost' => 60, 'priority' => 1]);

        $experience = app(LoyaltyRewardServiceInterface::class)->customerExperiencePayload($customer);
        $this->assertSame((int) $near->id, $experience['next_reward']['reward_id']);
        $this->assertSame(20, $experience['next_reward']['points_needed']);
        $this->assertSame(66, $experience['next_reward']['progress_percent']);
    }

    public function test_ready_progress_when_reward_already_affordable(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 150,
        ]);
        $reward = LoyaltyReward::factory()->fixed('10.00')->create(['points_cost' => 100]);

        $next = app(LoyaltyRewardServiceInterface::class)->customerExperiencePayload($customer)['next_reward'];
        $this->assertSame('ready', $next['state']);
        $this->assertSame((int) $reward->id, $next['reward_id']);
        $this->assertSame(100, $next['progress_percent']);
        $this->assertSame(0, $next['points_needed']);
    }

    public function test_no_rewards_disabled_debt_scheduled_and_limit_states(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => -25,
            'lifetime_earned_points' => 100,
            'lifetime_redeemed_points' => 80,
        ]);

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.account.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('data.display_available_points', 0)
            ->assertJsonPath('data.has_points_debt', true)
            ->assertJsonPath('data.debt_message', 'Points adjustment pending')
            ->assertJsonPath('data.next_reward.state', 'debt');

        config()->set('loyalty.enabled', false);
        $this->getJson(route('api.v1.account.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('data.earning_enabled', false)
            ->assertJsonPath('data.rewards', []);

        config()->set('loyalty.enabled', true);
        LoyaltyAccount::query()->where('customer_id', $customer->id)->update(['available_points' => 200]);

        $scheduled = LoyaltyReward::factory()->fixed('5.00')->create([
            'points_cost' => 10,
            'starts_at' => now()->addDays(3),
        ]);
        $limited = LoyaltyReward::factory()->fixed('5.00')->create([
            'points_cost' => 10,
            'usage_limit_per_customer' => 1,
        ]);
        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => LoyaltyAccount::query()->where('customer_id', $customer->id)->value('id'),
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::Redeem,
            'points' => -10,
            'reason_code' => 'order_loyalty_redeem',
            'idempotency_key' => 'redeem:order:1:reward:'.$limited->id,
            'metadata' => ['reward_id' => $limited->id],
            'occurred_at' => now(),
        ]);

        $cards = collect(app(LoyaltyRewardServiceInterface::class)->availableRewardsForCustomer($customer, '100.00'));
        $this->assertSame('scheduled', $cards->firstWhere('id', $scheduled->id)['state']);
        $this->assertStringContainsString('Available from', $cards->firstWhere('id', $scheduled->id)['unavailable_message']);
        $this->assertSame('limit_reached', $cards->firstWhere('id', $limited->id)['state']);
        $this->assertSame('Redemption limit reached', $cards->firstWhere('id', $limited->id)['unavailable_message']);
    }

    public function test_checkout_summary_exposes_server_preview_and_remaining_points(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 120,
        ]);
        $reward = LoyaltyReward::factory()->fixed('25.00')->create(['points_cost' => 40]);
        $variant = $this->makePurchasableVariant('80.00');

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson(route('api.v1.cart.loyalty-reward.apply'), [
            'loyalty_reward_id' => $reward->id,
        ])->assertSuccessful();

        $summary = $this->getJson(route('api.v1.cart.show'))->json('meta.summary');
        $this->assertSame('25.00', $summary['loyalty_discount']);
        $this->assertSame(40, $summary['loyalty_reward']['points_cost']);
        $this->assertSame(80, $summary['loyalty_reward']['remaining_points_after']);
        $this->assertNotEmpty($summary['loyalty_reward']['benefit_label']);
        $this->assertArrayNotHasKey('config', $summary['loyalty_reward']);

        $this->deleteJson(route('api.v1.cart.loyalty-reward.clear'))->assertSuccessful();
        $this->assertNull($this->getJson(route('api.v1.cart.show'))->json('meta.summary.loyalty_reward'));
    }

    public function test_order_feedback_does_not_predict_async_earning(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal' => '50.00',
            'discount_total' => '0.00',
            'taxable_amount' => '50.00',
            'loyalty_reward_name_snapshot' => 'Treat',
            'loyalty_reward_points_cost_snapshot' => 30,
            'loyalty_discount_amount' => '12.00',
            'completed_at' => now(),
        ]);

        $feedback = app(LoyaltyServiceInterface::class)->orderFeedback($order);
        $this->assertNull($feedback['points_earned']);
        $this->assertTrue($feedback['earning_pending']);
        $this->assertSame(30, $feedback['points_redeemed']);

        app(LoyaltyServiceInterface::class)->awardForOrder($order);
        $feedback = app(LoyaltyServiceInterface::class)->orderFeedback($order->fresh());
        $this->assertSame(50, $feedback['points_earned']);
        $this->assertFalse($feedback['earning_pending']);

        Sanctum::actingAs($customer);
        $this->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('data.loyalty_feedback.points_earned', 50)
            ->assertJsonPath('data.loyalty_feedback.earning_pending', false)
            ->assertJsonMissingPath('data.loyalty_feedback.idempotency_key');
    }

    public function test_activity_payload_is_customer_safe_and_isolated(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $account = LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 10,
        ]);
        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $account->id,
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::Earn,
            'points' => 10,
            'reason_code' => 'order_completed',
            'description' => 'Earned for order ORD-1',
            'idempotency_key' => 'earn:order:99',
            'metadata' => ['order_number' => 'ORD-1', 'eligible_amount' => '10.00'],
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($customer);
        $txn = $this->getJson(route('api.v1.account.loyalty.show'))->json('data.recent_transactions.0');
        $this->assertSame('Points earned', $txn['label']);
        $this->assertSame('ORD-1', $txn['order_number']);
        $this->assertArrayNotHasKey('metadata', $txn);
        $this->assertArrayNotHasKey('idempotency_key', $txn);
        $this->assertArrayNotHasKey('source_type', $txn);

        Sanctum::actingAs($other);
        $this->getJson(route('api.v1.account.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('data.recent_transactions', []);
    }

    public function test_free_product_benefit_label(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 200,
        ]);
        $variant = $this->makePurchasableVariant('70.00');
        $reward = LoyaltyReward::factory()->create([
            'reward_type' => LoyaltyRewardType::FreeBaseProduct,
            'points_cost' => 80,
            'config' => [],
        ]);
        $reward->products()->sync([(int) $variant->product_id]);

        $card = collect(app(LoyaltyRewardServiceInterface::class)->availableRewardsForCustomer(
            $customer,
            '70.00',
            [[
                'product_id' => (int) $variant->product_id,
                'product_variant_id' => (int) $variant->id,
                'product_category_id' => (int) $variant->product->product_category_id,
                'quantity' => 1,
                'unit_price' => '70.00',
                'line_subtotal' => '70.00',
                'base_unit_price' => '70.00',
                'base_line_subtotal' => '70.00',
                'addon_line_subtotal' => '0.00',
                'add_ons' => [],
            ]],
        ))->firstWhere('id', $reward->id);

        $this->assertStringContainsString('Free', $card['benefit_label']);
        $this->assertTrue($card['eligible']);
    }

    public function test_personalisation_summary_is_safe_and_present(): void
    {
        $customer = User::factory()->customer()->create();
        LoyaltyAccount::factory()->create([
            'customer_id' => $customer->id,
            'available_points' => 90,
        ]);
        LoyaltyReward::factory()->fixed('10.00')->create(['points_cost' => 100]);

        Sanctum::actingAs($customer);
        $summary = $this->getJson(route('api.v1.account.loyalty.show'))->json('data.personalisation_summary');

        $this->assertSame(90, $summary['available_points']);
        $this->assertFalse($summary['reward_available']);
        $this->assertNotNull($summary['nearest_reward_id']);
        $this->assertIsInt($summary['nearest_reward_progress_percent']);
        $this->assertArrayNotHasKey('ledger', $summary);
    }

    protected function makePurchasableVariant(string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create([
            'name' => fake()->unique()->words(2, true),
        ]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => 'bar',
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);
    }
}
