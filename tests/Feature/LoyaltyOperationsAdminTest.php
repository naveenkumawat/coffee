<?php

namespace Tests\Feature;

use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use App\Enums\LoyaltyRewardStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\CustomerBehaviourEvent;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\User;
use App\Services\Loyalty\LoyaltyReportingServiceInterface;
use App\Services\Loyalty\LoyaltyRewardCatalogServiceInterface;
use App\Services\Loyalty\LoyaltyServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoyaltyOperationsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('loyalty.enabled', true);
        config()->set('loyalty.effective_at', null);
    }

    public function test_manager_can_view_loyalty_operations_dashboard_and_non_admin_roles_are_denied(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $operator = User::factory()->operator()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.loyalty-operations.index'))
            ->assertOk()
            ->assertSee('Outstanding points')
            ->assertSee('not a currency liability', false);

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.loyalty-operations.index'))
            ->assertForbidden();

        $this->actingAs($operator, 'admin')
            ->get(route('administrator.loyalty-operations.index'))
            ->assertForbidden();
    }

    public function test_dashboard_aggregates_earn_redeem_restore_adjustment_debt_and_outstanding(): void
    {
        $manager = User::factory()->manager()->create();
        $customerA = User::factory()->customer()->create();
        $customerB = User::factory()->customer()->create();
        $loyalty = app(LoyaltyServiceInterface::class);

        $accountA = $loyalty->ensureAccount($customerA);
        $accountB = $loyalty->ensureAccount($customerB);

        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $accountA->id,
            'customer_id' => $customerA->id,
            'type' => LoyaltyTransactionType::Earn,
            'points' => 100,
            'reason_code' => 'order_earn',
            'occurred_at' => now(),
        ]);
        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $accountA->id,
            'customer_id' => $customerA->id,
            'type' => LoyaltyTransactionType::Redeem,
            'points' => -40,
            'reason_code' => 'order_loyalty_redeem',
            'metadata' => ['reward_id' => 9],
            'occurred_at' => now(),
        ]);
        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $accountA->id,
            'customer_id' => $customerA->id,
            'type' => LoyaltyTransactionType::Reversal,
            'points' => 40,
            'reason_code' => 'order_loyalty_restore',
            'occurred_at' => now(),
        ]);
        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $accountA->id,
            'customer_id' => $customerA->id,
            'type' => LoyaltyTransactionType::Reversal,
            'points' => -100,
            'reason_code' => 'order_earn_reversal',
            'occurred_at' => now(),
        ]);
        $loyalty->adjustPoints($customerA, $manager, 25, 'Goodwill', 'adj:ops:pos');
        $loyalty->adjustPoints($customerB, $manager, -10, 'Debt seed', 'adj:ops:neg');

        $accountA->forceFill([
            'available_points' => 80,
            'lifetime_earned_points' => 100,
            'lifetime_redeemed_points' => 40,
        ])->save();
        $accountB->forceFill([
            'available_points' => -10,
            'lifetime_adjusted_points' => -10,
        ])->save();

        $report = app(LoyaltyReportingServiceInterface::class)->buildOperationsDashboard([
            'preset' => 'today',
        ]);
        $summary = $report['summary'];

        $this->assertSame(100, $summary['earned_points']);
        $this->assertSame(40, $summary['redeemed_points']);
        $this->assertSame(40, $summary['restored_points']);
        $this->assertSame(100, $summary['reversed_earn_points']);
        $this->assertSame(25, $summary['adjustment_positive_points']);
        $this->assertSame(10, $summary['adjustment_negative_points']);
        $this->assertSame(15, $summary['adjustment_net_points']);
        $this->assertSame(80, $summary['outstanding_points']);
        $this->assertSame(1, $summary['positive_balance_customers']);
        $this->assertSame(1, $summary['debt_customers']);
        $this->assertSame(10, $summary['debt_points']);
        $this->assertSame(80.0, $summary['average_positive_balance']);
    }

    public function test_zero_denominator_redemption_rate_is_null_and_renders_as_em_dash(): void
    {
        $manager = User::factory()->manager()->create();

        $report = app(LoyaltyReportingServiceInterface::class)->buildOperationsDashboard([
            'preset' => 'today',
        ]);

        $this->assertNull($report['summary']['redemption_rate_percent']);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.loyalty-operations.index', ['preset' => 'today']))
            ->assertOk()
            ->assertSee('—', false);
    }

    public function test_date_range_filter_excludes_out_of_range_earn_transactions(): void
    {
        $customer = User::factory()->customer()->create();
        $account = app(LoyaltyServiceInterface::class)->ensureAccount($customer);

        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $account->id,
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::Earn,
            'points' => 50,
            'occurred_at' => now()->subDays(20),
        ]);
        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $account->id,
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::Earn,
            'points' => 12,
            'occurred_at' => now(),
        ]);

        $report = app(LoyaltyReportingServiceInterface::class)->buildOperationsDashboard([
            'preset' => 'today',
        ]);

        $this->assertSame(12, $report['summary']['earned_points']);
    }

    public function test_reward_performance_uses_behaviour_and_redemption_evidence_only(): void
    {
        $customer = User::factory()->customer()->create();
        $account = app(LoyaltyServiceInterface::class)->ensureAccount($customer);
        $reward = LoyaltyReward::factory()->fixed('10.00')->create(['points_cost' => 25]);

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::LoyaltyRewardViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'customer_id' => $customer->id,
            'visitor_key' => 'visitor-ops-1',
            'metadata' => ['reward_id' => (int) $reward->id],
            'occurred_at' => now(),
        ]);
        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::LoyaltyRewardSelected->value,
            'source' => BehaviourEventSource::Client->value,
            'customer_id' => $customer->id,
            'visitor_key' => 'visitor-ops-1',
            'metadata' => ['reward_id' => (int) $reward->id],
            'occurred_at' => now(),
        ]);
        LoyaltyPointTransaction::factory()->create([
            'loyalty_account_id' => $account->id,
            'customer_id' => $customer->id,
            'type' => LoyaltyTransactionType::Redeem,
            'points' => -25,
            'reason_code' => 'order_loyalty_redeem',
            'metadata' => ['reward_id' => (int) $reward->id],
            'occurred_at' => now(),
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'loyalty_reward_id' => $reward->id,
            'loyalty_discount_amount' => '10.00',
            'loyalty_reward_points_cost_snapshot' => 25,
            'loyalty_reward_type_snapshot' => $reward->reward_type->value,
            'placed_at' => now(),
        ]);

        $report = app(LoyaltyReportingServiceInterface::class)->buildOperationsDashboard([
            'preset' => 'today',
        ]);
        $row = collect($report['reward_performance'])->firstWhere('reward_id', (int) $reward->id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['views']);
        $this->assertSame(1, $row['selections']);
        $this->assertSame(1, $row['redemptions']);
        $this->assertSame(25, $row['points_consumed']);
        $this->assertSame('10.00', $row['discount_value']);
        $this->assertSame(100.0, $row['view_to_select_percent']);
        $this->assertSame(100.0, $row['select_to_redeem_percent']);
    }

    public function test_customer_lookup_and_hardened_adjustment_flow(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create(['name' => 'Lookup Guest']);
        $loyalty = app(LoyaltyServiceInterface::class);
        $loyalty->ensureAccount($customer);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.users.show', $customer))
            ->assertOk()
            ->assertSee('Loyalty')
            ->assertSee('Adjust points');

        $this->actingAs($manager, 'admin')
            ->from(route('administrator.users.show', $customer))
            ->post(route('administrator.users.loyalty-adjust', $customer), [
                'points' => -15,
                'reason' => '',
                'idempotency_key' => 'adj:ops:missing-reason',
                'confirmed' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('reason');

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.users.loyalty-adjust', $customer), [
                'points' => -15,
                'reason' => 'Ops debt',
                'idempotency_key' => 'adj:ops:debt',
                'confirmed' => '1',
            ])
            ->assertRedirect(route('administrator.users.show', $customer));

        $this->assertSame(-15, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));

        $txn = LoyaltyPointTransaction::query()
            ->where('idempotency_key', 'adj:ops:debt')
            ->firstOrFail();
        $this->assertSame(LoyaltyTransactionType::Adjustment, $txn->type);
        $this->assertSame($manager->id, (int) ($txn->metadata['actor_id'] ?? 0));
        $this->assertSame('Ops debt', $txn->description);

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.users.loyalty-adjust', $customer), [
                'points' => -15,
                'reason' => 'Ops debt',
                'idempotency_key' => 'adj:ops:debt',
                'confirmed' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(1, LoyaltyPointTransaction::query()->where('idempotency_key', 'adj:ops:debt')->count());
        $this->assertSame(-15, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.users.loyalty-adjust', $customer), [
                'points' => 20,
                'reason' => 'Compensating recovery',
                'idempotency_key' => 'adj:ops:recover',
                'confirmed' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(5, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.users.show', $customer))
            ->assertOk()
            ->assertDontSee('Loyalty debt');

        $this->assertNull(
            LoyaltyPointTransaction::query()->whereKey($txn->id)->first()?->getAttributes()['deleted_at'] ?? null,
        );
        $this->assertDatabaseHas('loyalty_point_transactions', [
            'id' => $txn->id,
            'description' => 'Ops debt',
            'points' => -15,
        ]);
    }

    public function test_adjustment_audit_page_lists_actor_and_has_no_edit_delete_routes(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        app(LoyaltyServiceInterface::class)->adjustPoints($customer, $manager, 8, 'Audit seed', 'adj:ops:audit');

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.loyalty-operations.adjustments', ['preset' => 'today']))
            ->assertOk()
            ->assertSee('Audit seed')
            ->assertSee($manager->name)
            ->assertSee('cannot be edited or deleted', false);

        $this->assertFalse(
            collect(Route::getRoutes())->contains(
                fn ($route): bool => str_contains((string) $route->getName(), 'loyalty')
                    && in_array('PUT', $route->methods(), true)
                    && str_contains($route->uri(), 'adjustment'),
            ),
        );
    }

    public function test_bulk_pause_and_activate_with_partial_failure_for_archived(): void
    {
        $manager = User::factory()->manager()->create();
        $active = LoyaltyReward::factory()->fixed('5.00')->create([
            'status' => LoyaltyRewardStatus::Active,
            'name' => 'Bulk Active',
        ]);
        $paused = LoyaltyReward::factory()->fixed('5.00')->create([
            'status' => LoyaltyRewardStatus::Paused,
            'name' => 'Bulk Paused',
        ]);
        $archived = LoyaltyReward::factory()->fixed('5.00')->create([
            'status' => LoyaltyRewardStatus::Active,
            'name' => 'Bulk Archive Candidate',
        ]);
        app(LoyaltyRewardCatalogServiceInterface::class)->archive($archived);

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.loyalty-rewards.bulk-status'), [
                'reward_ids' => [$active->id, $archived->id],
                'status' => LoyaltyRewardStatus::Paused->value,
                'confirmed' => '1',
            ])
            ->assertRedirect(route('administrator.loyalty-rewards.index'))
            ->assertSessionHas('status');

        $this->assertSame(LoyaltyRewardStatus::Paused, $active->fresh()->status);
        $this->assertTrue($archived->fresh()->trashed());

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.loyalty-rewards.bulk-status'), [
                'reward_ids' => [$paused->id],
                'status' => LoyaltyRewardStatus::Active->value,
                'confirmed' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(LoyaltyRewardStatus::Active, $paused->fresh()->status);

        $this->actingAs($manager, 'admin')
            ->from(route('administrator.loyalty-rewards.index'))
            ->post(route('administrator.loyalty-rewards.bulk-status'), [
                'reward_ids' => [],
                'status' => LoyaltyRewardStatus::Paused->value,
                'confirmed' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('reward_ids');
    }

    public function test_archive_keeps_historical_order_snapshot_readable_and_unchanged(): void
    {
        $manager = User::factory()->manager()->create();
        $reward = LoyaltyReward::factory()->fixed('12.00')->create([
            'name' => 'Snapshot Reward',
            'points_cost' => 30,
        ]);
        $order = Order::factory()->create([
            'loyalty_reward_id' => $reward->id,
            'loyalty_reward_name_snapshot' => 'Snapshot Reward',
            'loyalty_reward_points_cost_snapshot' => 30,
            'loyalty_discount_amount' => '12.00',
        ]);

        $this->actingAs($manager, 'admin')
            ->delete(route('administrator.loyalty-rewards.destroy', $reward))
            ->assertRedirect(route('administrator.loyalty-rewards.index'));

        $order->refresh();
        $this->assertSame('Snapshot Reward', $order->loyalty_reward_name_snapshot);
        $this->assertSame(30, (int) $order->loyalty_reward_points_cost_snapshot);
        $this->assertSame('12.00', (string) $order->loyalty_discount_amount);
        $this->assertTrue($reward->fresh()->trashed());
        $this->assertSame('Snapshot Reward', LoyaltyReward::withTrashed()->find($reward->id)?->name);
    }

    public function test_exports_respect_auth_and_csv_headers(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.loyalty-operations.export.ledger'))
            ->assertForbidden();

        $response = $this->actingAs($manager, 'admin')
            ->get(route('administrator.loyalty-operations.export.ledger', ['preset' => 'today']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('occurred_at', $response->streamedContent());
        $this->assertStringNotContainsString('idempotency_key', $response->streamedContent());
    }
}
