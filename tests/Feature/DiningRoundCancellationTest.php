<?php

namespace Tests\Feature;

use App\Enums\DiningRoundCancellationReason;
use App\Enums\DiningSessionStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Events\Realtime\DiningOpsSignalBroadcasted;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderInventoryConsumption;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningRoundCancellationPolicy;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiningRoundCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_can_cancel_accepted_round_before_preparation_and_reverses_inventory(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, null, $waiter);
        $variant = $this->makeVariant(PreparationStation::Bar, 'Cancel Latte');
        $ingredient = $variant->recipe->lines->first()->ingredient;
        $stockBeforeConsume = (string) $ingredient->fresh()->current_stock;

        $order = app(OrderServiceInterface::class)->placeDiningRound($waiter, $session, [
            ['product_variant_id' => $variant->id, 'quantity' => 1],
        ]);
        $order = app(DiningSessionServiceInterface::class)->acceptRound($session, $order, $waiter);

        Sanctum::actingAs($waiter);
        $this->postJson(route('api.v1.waiter.sessions.rounds.cancel', [$session->id, $order->id]))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.rounds.0.can_cancel', false);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame(DiningSessionStatus::Open, $session->fresh()->status);

        $this->assertTrue(
            OrderInventoryConsumption::query()
                ->where('order_id', $order->id)
                ->whereNotNull('reversed_at')
                ->exists(),
        );
        $this->assertTrue(
            InventoryTransaction::query()
                ->where('ingredient_id', $ingredient->id)
                ->where('transaction_type', InventoryTransactionType::SaleReversal->value)
                ->exists(),
        );
        $this->assertSame($stockBeforeConsume, (string) $ingredient->fresh()->current_stock);
    }

    public function test_privileged_cancel_after_preparing_requires_reason_and_keeps_consumption(): void
    {
        $this->enableDining();

        [$waiter, $session, $order, $ingredient] = $this->openDiningRound();
        $this->advanceTickets($order, stopAt: OrderPreparationStatus::Preparing);
        $stockAfterPrep = (string) $ingredient->fresh()->current_stock;

        Sanctum::actingAs($waiter);
        $this->postJson(route('api.v1.waiter.sessions.rounds.cancel', [$session->id, $order->id]))
            ->assertStatus(422);

        $operator = User::factory()->operator()->create();
        $dining = app(DiningSessionServiceInterface::class);

        try {
            $dining->cancelRound($session, $order->fresh(), $operator);
            $this->fail('Expected ValidationException for missing reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $dining->cancelRound(
            $session,
            $order->fresh(),
            $operator,
            DiningRoundCancellationReason::PreparationError->value,
            'Spill on station',
        );

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertFalse(
            OrderInventoryConsumption::query()
                ->where('order_id', $order->id)
                ->whereNotNull('reversed_at')
                ->exists(),
        );
        $this->assertSame($stockAfterPrep, (string) $ingredient->fresh()->current_stock);
        $this->assertStringContainsString(
            DiningRoundCancellationReason::PreparationError->value,
            (string) $order->fresh()->statusHistory()
                ->where('to_status', OrderStatus::Cancelled->value)
                ->latest('id')
                ->value('notes'),
        );
    }

    public function test_ready_unserved_privileged_cancel_allowed_with_reason(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRound();
        $this->advanceTickets($order, stopAt: OrderPreparationStatus::Ready);

        Sanctum::actingAs($waiter);
        $this->getJson(route('api.v1.waiter.sessions.show', $session->id))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.can_cancel', false)
            ->assertJsonPath('data.rounds.0.can_void', false);

        $operator = User::factory()->operator()->create();
        $decision = app(DiningRoundCancellationPolicy::class)
            ->evaluate($session, $order->fresh(), $operator);
        $this->assertTrue($decision['can_cancel']);
        $this->assertTrue($decision['cancel_requires_reason']);

        app(DiningSessionServiceInterface::class)->cancelRound(
            $session,
            $order->fresh(),
            $operator,
            DiningRoundCancellationReason::QualityIssue->value,
        );

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $bill = app(DiningSessionServiceInterface::class)->runningBill($session->fresh(['orders']));
        $this->assertSame([], $bill['rounds']);
        $this->assertSame('0.00', $bill['total']);
    }

    public function test_served_round_cancellation_blocked_and_served_metadata_preserved(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRound();
        $this->advanceTickets($order, stopAt: OrderPreparationStatus::Ready);
        app(DiningSessionServiceInterface::class)->markRoundServed($session, $order->fresh(), $waiter);

        $order->refresh();
        $servedAt = $order->served_at;
        $servedBy = $order->served_by_user_id;
        $this->assertNotNull($servedAt);

        $admin = User::factory()->manager()->create();
        try {
            app(DiningSessionServiceInterface::class)->cancelRound(
                $session,
                $order->fresh(),
                $admin,
                DiningRoundCancellationReason::StaffError->value,
            );
            $this->fail('Expected ValidationException for served round.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }

        $order->refresh();
        $this->assertNotSame(OrderStatus::Cancelled, $order->status);
        $this->assertTrue($order->served_at->equalTo($servedAt));
        $this->assertSame($servedBy, $order->served_by_user_id);

        Sanctum::actingAs($waiter);
        $this->getJson(route('api.v1.waiter.sessions.show', $session->id))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.can_cancel', false)
            ->assertJsonPath('data.rounds.0.can_void', false)
            ->assertJsonPath('data.rounds.0.served', true);
    }

    public function test_bill_requested_and_payment_confirmed_block_cancellation(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRound();
        Sanctum::actingAs($waiter);

        app(DiningSessionServiceInterface::class)->requestBill($session, $waiter);
        $this->postJson(route('api.v1.waiter.sessions.rounds.cancel', [$session->id, $order->id]))
            ->assertStatus(422);

        $session = app(DiningSessionServiceInterface::class)->reopenSession(
            $session->fresh(),
            $waiter,
            'Resume after mistaken bill request',
        );
        $this->assertSame(DiningSessionStatus::Open, $session->status);

        app(DiningSessionServiceInterface::class)->requestBill($session, $waiter);
        app(DiningSessionServiceInterface::class)->setPaymentMethod($session->fresh(), 'cash');
        app(DiningSessionServiceInterface::class)->markCashReceived($session->fresh(), $waiter);

        $this->assertSame(PaymentStatus::Confirmed, $session->fresh()->payment_status);
        $this->postJson(route('api.v1.waiter.sessions.rounds.cancel', [$session->id, $order->id]))
            ->assertStatus(422);
        $this->assertNotSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    public function test_closed_session_blocks_cancellation(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRound();
        Sanctum::actingAs($waiter);

        app(DiningSessionServiceInterface::class)->requestBill($session, $waiter);
        app(DiningSessionServiceInterface::class)->setPaymentMethod($session->fresh(), 'cash');
        app(DiningSessionServiceInterface::class)->markCashReceived($session->fresh(), $waiter);
        app(DiningSessionServiceInterface::class)->closeSession($session->fresh(), $waiter);

        $this->postJson(route('api.v1.waiter.sessions.rounds.cancel', [$session->id, $order->id]))
            ->assertStatus(422);
    }

    public function test_customer_barista_chef_denied_and_idempotent_cancel(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRound();

        foreach ([
            User::factory()->customer()->create(),
            User::factory()->barista()->create(),
            User::factory()->chef()->create(),
        ] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson(route('api.v1.waiter.sessions.rounds.cancel', [$session->id, $order->id]))
                ->assertForbidden();
        }

        Sanctum::actingAs($waiter);
        Event::fake([DiningOpsSignalBroadcasted::class]);

        $this->postJson(route('api.v1.waiter.sessions.rounds.cancel', [$session->id, $order->id]))
            ->assertOk();

        $cancelledSignals = collect(Event::dispatched(DiningOpsSignalBroadcasted::class))
            ->filter(fn (array $args): bool => ($args[0]->payload['type'] ?? null) === 'round.cancelled')
            ->count();
        $this->assertSame(1, $cancelledSignals);

        $this->postJson(route('api.v1.waiter.sessions.rounds.cancel', [$session->id, $order->id]))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.status', OrderStatus::Cancelled->value);

        $this->assertSame(
            1,
            collect(Event::dispatched(DiningOpsSignalBroadcasted::class))
                ->filter(fn (array $args): bool => ($args[0]->payload['type'] ?? null) === 'round.cancelled')
                ->count(),
        );
        $this->assertSame(1, $order->fresh()->statusHistory()->where('to_status', OrderStatus::Cancelled->value)->count());
    }

    public function test_broadcast_failure_does_not_fail_cancellation_and_other_round_unaffected(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRound();
        $variant = $this->makeVariant(PreparationStation::Bar, 'Second Drink');

        Sanctum::actingAs($waiter);
        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $session->id), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $session->id), [
            'idempotency_key' => 'l12-round-2',
        ])->assertCreated();

        Event::listen(DiningOpsSignalBroadcasted::class, function (): void {
            throw new \RuntimeException('broadcast exploded');
        });

        $this->postJson(route('api.v1.waiter.sessions.rounds.cancel', [$session->id, $order->id]))
            ->assertOk();

        $session->refresh()->load('orders');
        $this->assertSame(2, $session->orders->count());
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $active = $session->orders->first(fn (Order $round): bool => $round->id !== $order->id);
        $this->assertNotNull($active);
        $this->assertNotSame(OrderStatus::Cancelled, $active->status);
        $this->assertSame(DiningSessionStatus::Open, $session->status);
    }

    public function test_historical_dining_round_without_cancel_reason_remains_readable(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRound();
        Sanctum::actingAs($waiter);

        $this->getJson(route('api.v1.waiter.sessions.show', $session->id))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.id', $order->id)
            ->assertJsonPath('data.rounds.0.can_cancel', true)
            ->assertJsonPath('data.rounds.0.cancel_requires_reason', false);
    }

    /**
     * @return array{0: User, 1: DiningSession, 2: Order, 3: Ingredient}
     */
    protected function openDiningRound(): array
    {
        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, null, $waiter);
        $variant = $this->makeVariant(PreparationStation::Bar, 'Cancel Latte');

        $order = app(OrderServiceInterface::class)->placeDiningRound($waiter, $session, [
            ['product_variant_id' => $variant->id, 'quantity' => 1],
        ]);
        $order = app(DiningSessionServiceInterface::class)->acceptRound($session, $order, $waiter);

        $ingredient = Ingredient::query()
            ->whereIn('id', OrderInventoryConsumption::query()->where('order_id', $order->id)->pluck('ingredient_id'))
            ->firstOrFail();

        return [$waiter, $session->fresh(), $order->fresh(['preparations']), $ingredient];
    }

    protected function advanceTickets(Order $order, OrderPreparationStatus $stopAt): void
    {
        $barista = User::factory()->barista()->create();
        $chef = User::factory()->chef()->create();
        $prep = app(OrderPreparationServiceInterface::class);
        $order->loadMissing('preparations');

        foreach ($order->preparations as $ticket) {
            $actor = $ticket->station === PreparationStation::Kitchen ? $chef : $barista;
            $prep->transition($ticket, $actor, OrderPreparationStatus::Accepted);
            if ($stopAt === OrderPreparationStatus::Accepted) {
                continue;
            }
            $prep->transition($ticket->fresh(), $actor, OrderPreparationStatus::Preparing);
            if ($stopAt === OrderPreparationStatus::Preparing) {
                continue;
            }
            $prep->transition($ticket->fresh(), $actor, OrderPreparationStatus::Ready);
        }
    }

    protected function makeVariant(PreparationStation $station, string $name): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => $station,
        ]);

        return ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => '10.00',
            'is_active' => true,
            'is_available' => true,
        ])->fresh(['recipe.lines.ingredient']);
    }

    protected function enableDining(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            [
                'section' => WebsiteSettingKey::FulfilmentDineInEnabled->section(),
                'value_type' => WebsiteSettingKey::FulfilmentDineInEnabled->valueType(),
                'value' => '1',
            ],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::OrderingManualClosed->value],
            [
                'section' => WebsiteSettingKey::OrderingManualClosed->section(),
                'value_type' => WebsiteSettingKey::OrderingManualClosed->valueType(),
                'value' => '0',
            ],
        );
    }
}
