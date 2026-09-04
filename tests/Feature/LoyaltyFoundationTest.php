<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\LoyaltyTransactionType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Dining\DiningPaymentConfirmed;
use App\Events\Order\OrderStatusChanged;
use App\Jobs\AwardLoyaltyPointsForOrderJob;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyPointTransaction;
use App\Models\Order;
use App\Models\User;
use App\Services\Loyalty\LoyaltyServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoyaltyFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('loyalty.enabled', true);
        config()->set('loyalty.effective_at', null);
        config()->set('loyalty.earning.points_per_currency_unit', 1);
        config()->set('loyalty.earning.currency_unit', 1);
        config()->set('loyalty.earning.minimum_eligible_amount', 0);
    }

    public function test_account_creation_starts_at_zero_balance(): void
    {
        $customer = User::factory()->customer()->create();
        $account = app(LoyaltyServiceInterface::class)->ensureAccount($customer);

        $this->assertSame(0, (int) $account->available_points);
        $this->assertSame(0, (int) $account->lifetime_earned_points);
        $this->assertSame(0, (int) $account->lifetime_redeemed_points);
        $this->assertDatabaseHas('loyalty_accounts', [
            'customer_id' => $customer->id,
            'available_points' => 0,
        ]);
    }

    public function test_eligible_completed_paid_takeaway_earns_points(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->makeEligibleOrder($customer, [
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'taxable_amount' => '100.00',
            'delivery_fee_amount' => null,
        ]);

        $result = app(LoyaltyServiceInterface::class)->awardForOrder($order);

        $this->assertTrue($result['awarded']);
        $this->assertSame(100, $result['points']);
        $this->assertDatabaseHas('loyalty_accounts', [
            'customer_id' => $customer->id,
            'available_points' => 100,
            'lifetime_earned_points' => 100,
            'lifetime_redeemed_points' => 0,
        ]);
    }

    public function test_eligible_delivery_excludes_delivery_fee_from_earning(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->makeEligibleOrder($customer, [
            'fulfilment_method' => OrderFulfilmentMethod::Delivery,
            'subtotal' => '80.00',
            'discount_total' => '0.00',
            'taxable_amount' => '80.00',
            'delivery_fee_amount' => '40.00',
            'total_amount' => '120.00',
        ]);

        $result = app(LoyaltyServiceInterface::class)->awardForOrder($order);

        $this->assertTrue($result['awarded']);
        $this->assertSame(80, $result['points']);
    }

    public function test_discount_reduces_eligible_amount(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->makeEligibleOrder($customer, [
            'subtotal' => '100.00',
            'discount_total' => '25.00',
            'taxable_amount' => '75.00',
            'total_amount' => '75.00',
        ]);

        $this->assertSame(75, app(LoyaltyServiceInterface::class)->calculateEarnPoints($order));
    }

    public function test_rounding_is_deterministic_floor(): void
    {
        config()->set('loyalty.earning.currency_unit', 10);
        config()->set('loyalty.earning.points_per_currency_unit', 1);

        $customer = User::factory()->customer()->create();
        $order = $this->makeEligibleOrder($customer, [
            'taxable_amount' => '29.99',
            'subtotal' => '29.99',
        ]);

        $this->assertSame(2, app(LoyaltyServiceInterface::class)->calculateEarnPoints($order));
    }

    public function test_unpaid_incomplete_cancelled_guest_excluded(): void
    {
        $customer = User::factory()->customer()->create();
        $loyalty = app(LoyaltyServiceInterface::class);

        $unpaid = $this->makeEligibleOrder($customer, [
            'payment_status' => PaymentStatus::Pending,
        ]);
        $this->assertSame('unpaid', $loyalty->awardForOrder($unpaid)['reason']);

        $incomplete = $this->makeEligibleOrder($customer, [
            'status' => OrderStatus::Accepted,
            'completed_at' => null,
        ]);
        $this->assertSame('incomplete', $loyalty->awardForOrder($incomplete)['reason']);

        $cancelled = $this->makeEligibleOrder($customer, [
            'status' => OrderStatus::Cancelled,
            'completed_at' => now(),
        ]);
        $this->assertSame('incomplete', $loyalty->awardForOrder($cancelled)['reason']);

        $guest = $this->makeEligibleOrder(null, [
            'customer_id' => null,
        ]);
        $this->assertSame('guest', $loyalty->awardForOrder($guest)['reason']);
    }

    public function test_dining_earns_when_session_payment_confirmed(): void
    {
        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = DiningSession::query()->create([
            'session_number' => 'DS-TEST-1',
            'cafe_table_id' => $table->id,
            'customer_id' => $customer->id,
            'opened_by_user_id' => $customer->id,
            'status' => DiningSessionStatus::Paid,
            'guest_count' => 1,
            'table_name_snapshot' => $table->name ?? $table->code ?? 'Table',
            'opened_at' => now()->subHour(),
            'paid_at' => now(),
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal_amount' => '50.00',
            'discount_amount' => '0.00',
            'taxable_amount' => '50.00',
            'tax_amount' => '0.00',
            'total_amount' => '50.00',
        ]);

        $order = $this->makeEligibleOrder($customer, [
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
            'dining_session_id' => $session->id,
            'taxable_amount' => '50.00',
            'subtotal' => '50.00',
            'payment_status' => PaymentStatus::Pending,
        ]);

        $result = app(LoyaltyServiceInterface::class)->awardForOrder($order->fresh('diningSession'));

        $this->assertTrue($result['awarded']);
        $this->assertSame(50, $result['points']);
    }

    public function test_duplicate_award_is_idempotent(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->makeEligibleOrder($customer, ['taxable_amount' => '40.00', 'subtotal' => '40.00']);
        $loyalty = app(LoyaltyServiceInterface::class);

        $first = $loyalty->awardForOrder($order);
        $second = $loyalty->awardForOrder($order);

        $this->assertTrue($first['awarded']);
        $this->assertTrue($second['awarded']);
        $this->assertSame('idempotent', $second['reason']);
        $this->assertSame(1, LoyaltyPointTransaction::query()->where('type', LoyaltyTransactionType::Earn->value)->count());
        $this->assertSame(40, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));
    }

    public function test_reversal_creates_compensating_entry_and_is_idempotent(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->makeEligibleOrder($customer, ['taxable_amount' => '60.00', 'subtotal' => '60.00']);
        $loyalty = app(LoyaltyServiceInterface::class);

        $earn = $loyalty->awardForOrder($order);
        $earnTxn = LoyaltyPointTransaction::query()->findOrFail($earn['transaction_id']);

        $first = $loyalty->reverseOrderAward($order);
        $second = $loyalty->reverseOrderAward($order);

        $this->assertTrue($first['reversed']);
        $this->assertSame(60, $first['points']);
        $this->assertSame('idempotent', $second['reason']);
        $this->assertSame(LoyaltyTransactionType::Earn, $earnTxn->fresh()->type);
        $this->assertSame(60, (int) $earnTxn->fresh()->points);
        $this->assertSame(0, (int) LoyaltyAccount::query()->where('customer_id', $customer->id)->value('available_points'));
        $this->assertSame(2, LoyaltyPointTransaction::query()->where('source_id', $order->id)->count());
    }

    public function test_loyalty_disabled_and_effective_at_boundary(): void
    {
        $customer = User::factory()->customer()->create();
        $loyalty = app(LoyaltyServiceInterface::class);

        config()->set('loyalty.enabled', false);
        $order = $this->makeEligibleOrder($customer, ['taxable_amount' => '30.00', 'subtotal' => '30.00']);
        $this->assertSame('disabled', $loyalty->awardForOrder($order)['reason']);

        config()->set('loyalty.enabled', true);
        config()->set('loyalty.effective_at', now()->addDay()->toIso8601String());
        $this->assertSame('before_effective_at', $loyalty->awardForOrder($order)['reason']);

        config()->set('loyalty.effective_at', now()->subDay()->toIso8601String());
        $this->assertTrue($loyalty->awardForOrder($order)['awarded']);
    }

    public function test_no_retroactive_award_for_old_completed_orders_before_effective_at(): void
    {
        $customer = User::factory()->customer()->create();
        $old = $this->makeEligibleOrder($customer, [
            'taxable_amount' => '90.00',
            'subtotal' => '90.00',
            'completed_at' => now()->subDays(10),
        ]);

        config()->set('loyalty.effective_at', now()->subDay()->toIso8601String());

        $this->assertSame('before_effective_at', app(LoyaltyServiceInterface::class)->awardForOrder($old)['reason']);
        $this->assertSame(0, LoyaltyPointTransaction::query()->count());
    }

    public function test_order_completed_listener_dispatches_unique_job(): void
    {
        Bus::fake([AwardLoyaltyPointsForOrderJob::class]);

        $customer = User::factory()->customer()->create();
        $order = $this->makeEligibleOrder($customer);

        event(new OrderStatusChanged($order, OrderStatus::ReadyForPickup, OrderStatus::Completed));

        Bus::assertDispatched(AwardLoyaltyPointsForOrderJob::class, fn (AwardLoyaltyPointsForOrderJob $job): bool => $job->orderId === (int) $order->id);
    }

    public function test_dining_payment_listener_dispatches_award_jobs(): void
    {
        Bus::fake([AwardLoyaltyPointsForOrderJob::class]);

        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = DiningSession::query()->create([
            'session_number' => 'DS-TEST-2',
            'cafe_table_id' => $table->id,
            'customer_id' => $customer->id,
            'opened_by_user_id' => $customer->id,
            'status' => DiningSessionStatus::Paid,
            'guest_count' => 1,
            'table_name_snapshot' => $table->name ?? $table->code ?? 'Table',
            'opened_at' => now()->subHour(),
            'paid_at' => now(),
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal_amount' => '20.00',
            'discount_amount' => '0.00',
            'taxable_amount' => '20.00',
            'tax_amount' => '0.00',
            'total_amount' => '20.00',
        ]);
        $order = $this->makeEligibleOrder($customer, [
            'dining_session_id' => $session->id,
            'fulfilment_method' => OrderFulfilmentMethod::DineIn,
        ]);

        event(new DiningPaymentConfirmed($session->fresh(['orders']), $customer));

        Bus::assertDispatched(AwardLoyaltyPointsForOrderJob::class, fn (AwardLoyaltyPointsForOrderJob $job): bool => $job->orderId === (int) $order->id);
    }

    public function test_customer_api_privacy_and_payload(): void
    {
        $alice = User::factory()->customer()->create();
        $bob = User::factory()->customer()->create();
        $order = $this->makeEligibleOrder($alice, ['taxable_amount' => '15.00', 'subtotal' => '15.00']);
        app(LoyaltyServiceInterface::class)->awardForOrder($order);

        Sanctum::actingAs($bob);
        $this->getJson(route('api.v1.account.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('data.available_points', 0)
            ->assertJsonPath('data.lifetime_earned_points', 0);

        Sanctum::actingAs($alice);
        $this->getJson(route('api.v1.account.loyalty.show'))
            ->assertOk()
            ->assertJsonPath('data.available_points', 15)
            ->assertJsonPath('data.lifetime_earned_points', 15)
            ->assertJsonPath('data.lifetime_redeemed_points', 0)
            ->assertJsonMissingPath('data.recent_transactions.0.metadata')
            ->assertJsonMissingPath('data.recent_transactions.0.idempotency_key');

        $this->getJson(route('api.v1.account.loyalty.show'))->assertOk();
    }

    public function test_guest_cannot_access_loyalty_api(): void
    {
        $this->getJson(route('api.v1.account.loyalty.show'))->assertUnauthorized();
    }

    public function test_admin_can_view_customer_loyalty_on_user_show(): void
    {
        $admin = User::factory()->manager()->create();
        $customer = User::factory()->customer()->create();
        $order = $this->makeEligibleOrder($customer, ['taxable_amount' => '12.00', 'subtotal' => '12.00']);
        app(LoyaltyServiceInterface::class)->awardForOrder($order);

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.users.show', $customer))
            ->assertOk()
            ->assertSee('Loyalty')
            ->assertSee('12');
    }

    public function test_barista_cannot_view_users(): void
    {
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.users.show', $customer))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    protected function makeEligibleOrder(?User $customer, array $attrs = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => $customer?->id,
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Confirmed,
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'taxable_amount' => '100.00',
            'tax_amount' => '0.00',
            'total_amount' => '100.00',
            'completed_at' => now(),
            'payment_confirmed_at' => now(),
            'dining_session_id' => null,
        ], $attrs));
    }
}
