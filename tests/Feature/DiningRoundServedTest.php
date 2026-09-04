<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OperationalNotificationType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Events\Realtime\DiningOpsSignalBroadcasted;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\OperationalNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\OperationalNotification\OperationalNotificationServiceInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiningRoundServedTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_mark_served_before_all_stations_ready(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRoundWithStations();

        Sanctum::actingAs($waiter);

        $this->postJson(route('api.v1.waiter.sessions.rounds.served', [$session->id, $order->id]))
            ->assertStatus(422);

        $this->assertNull($order->fresh()->served_at);
    }

    public function test_waiter_can_mark_fully_ready_round_served_and_session_stays_open(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRoundWithStations();
        $this->markAllTicketsReady($order);

        Sanctum::actingAs($waiter);

        $this->postJson(route('api.v1.waiter.sessions.rounds.served', [$session->id, $order->id]))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::Open->value)
            ->assertJsonPath('data.rounds.0.served', true)
            ->assertJsonPath('data.rounds.0.ready_to_serve', false)
            ->assertJsonPath('data.rounds.0.can_mark_served', false);

        $order->refresh();
        $this->assertNotNull($order->served_at);
        $this->assertSame($waiter->id, $order->served_by_user_id);
        $this->assertSame(DiningSessionStatus::Open, $session->fresh()->status);
    }

    public function test_mark_served_is_idempotent_and_emits_realtime_signal_once(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRoundWithStations();
        $this->markAllTicketsReady($order);

        Event::fake([DiningOpsSignalBroadcasted::class]);
        Sanctum::actingAs($waiter);

        $this->postJson(route('api.v1.waiter.sessions.rounds.served', [$session->id, $order->id]))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.served', true);

        $servedAt = $order->fresh()->served_at;
        $this->assertNotNull($servedAt);

        $this->postJson(route('api.v1.waiter.sessions.rounds.served', [$session->id, $order->id]))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.served', true)
            ->assertJsonPath('data.rounds.0.can_mark_served', false);

        $this->assertTrue($order->fresh()->served_at->equalTo($servedAt));
        $this->assertSame($waiter->id, $order->fresh()->served_by_user_id);

        $servedSignals = collect(Event::dispatched(DiningOpsSignalBroadcasted::class))
            ->filter(fn (array $args): bool => ($args[0]->payload['type'] ?? null) === 'round.served')
            ->values();

        $this->assertCount(1, $servedSignals);
        $payload = $servedSignals->first()[0]->payload;
        $this->assertSame((int) $session->id, (int) ($payload['session_id'] ?? 0));
        $this->assertSame((int) $order->id, (int) ($payload['order_id'] ?? 0));
        $this->assertSame((int) $session->cafe_table_id, (int) ($payload['table_id'] ?? 0));
        $this->assertSame('served', $payload['state'] ?? null);
        $this->assertArrayNotHasKey('total_amount', $payload);
        $this->assertArrayNotHasKey('customer_phone', $payload);
        $this->assertArrayNotHasKey('payment_proof_path', $payload);
    }

    public function test_ready_to_serve_notification_resolves_and_reminder_stops_on_served(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRoundWithStations();
        $this->markAllTicketsReady($order);

        $notification = OperationalNotification::query()
            ->where('type', OperationalNotificationType::DiningReadyToServe->value)
            ->where('subject_id', $order->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertTrue($notification->action_required);
        $this->assertNull($notification->resolved_at);

        $recipient = $notification->recipients()->where('user_id', $waiter->id)->first();
        $this->assertNotNull($recipient);

        Sanctum::actingAs($waiter);
        $this->postJson(route('api.v1.waiter.sessions.rounds.served', [$session->id, $order->id]))
            ->assertOk();

        $notification->refresh();
        $this->assertNotNull($notification->resolved_at);
        $this->assertSame('served', $notification->resolution_action);
        $this->assertDatabaseHas('operational_notifications', [
            'id' => $notification->id,
            'type' => OperationalNotificationType::DiningReadyToServe->value,
        ]);

        $after = app(OperationalNotificationServiceInterface::class)
            ->recordPresentedReminder($recipient->fresh(['notification']));
        $this->assertSame((int) $recipient->reminder_count, (int) $after->reminder_count);
    }

    public function test_customer_barista_and_chef_cannot_mark_served(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRoundWithStations();
        $this->markAllTicketsReady($order);

        foreach ([
            User::factory()->customer()->create(),
            User::factory()->barista()->create(),
            User::factory()->chef()->create(),
        ] as $actor) {
            Sanctum::actingAs($actor);
            $this->postJson(route('api.v1.waiter.sessions.rounds.served', [$session->id, $order->id]))
                ->assertForbidden();
        }

        $this->assertNull($order->fresh()->served_at);
        $this->assertTrue($waiter->can('markServed', $session));
        $this->assertFalse(User::factory()->customer()->create()->can('markServed', $session));
        $this->assertFalse(User::factory()->barista()->create()->can('markServed', $session));
        $this->assertFalse(User::factory()->chef()->create()->can('markServed', $session));
    }

    public function test_non_dining_order_cannot_be_marked_served(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, null, $waiter);

        $retail = Order::factory()->create([
            'fulfilment_method' => OrderFulfilmentMethod::Takeaway,
            'dining_session_id' => null,
            'dining_round_number' => null,
        ]);

        $this->expectException(ValidationException::class);
        app(DiningSessionServiceInterface::class)->markRoundServed($session, $retail, $waiter);
    }

    public function test_served_round_allows_another_round_and_multi_round_state(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRoundWithStations();
        $this->markAllTicketsReady($order);

        Sanctum::actingAs($waiter);
        $this->postJson(route('api.v1.waiter.sessions.rounds.served', [$session->id, $order->id]))
            ->assertOk();

        $variant = $this->makeVariant(PreparationStation::Bar, 'Second Round Tea');
        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $session->id), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $session->id), [
            'idempotency_key' => 'l11-round-2',
        ])->assertCreated();

        $session->refresh()->load('orders.preparations');
        $this->assertSame(DiningSessionStatus::Open, $session->status);
        $this->assertSame(2, $session->orders->count());

        $round1 = $session->orders->firstWhere('id', $order->id);
        $round2 = $session->orders->sortBy('dining_round_number')->last();
        $this->assertNotNull($round1?->served_at);
        $this->assertNull($round2?->served_at);
        $this->assertSame(OrderStatus::Pending, $round2?->status);

        $tables = $this->getJson(route('api.v1.waiter.tables.index'))->assertOk()->json('data');
        $row = collect($tables)->firstWhere('id', $session->cafe_table_id);
        $this->assertSame('active', $row['display_state']);

        app(DiningSessionServiceInterface::class)->acceptRound($session->fresh(), $round2->fresh(), $waiter);
        $tables = $this->getJson(route('api.v1.waiter.tables.index'))->assertOk()->json('data');
        $row = collect($tables)->firstWhere('id', $session->cafe_table_id);
        $this->assertSame('preparing', $row['display_state']);

        $this->markAllTicketsReady($round2->fresh(['preparations']));
        $tables = $this->getJson(route('api.v1.waiter.tables.index'))->assertOk()->json('data');
        $row = collect($tables)->firstWhere('id', $session->cafe_table_id);
        $this->assertSame('ready_to_serve', $row['display_state']);

        $show = $this->getJson(route('api.v1.waiter.sessions.show', $session->id))->assertOk()->json('data');
        $rounds = collect($show['rounds']);
        $this->assertTrue((bool) $rounds->firstWhere('id', $round1->id)['served']);
        $this->assertFalse((bool) $rounds->firstWhere('id', $round1->id)['ready_to_serve']);
        $this->assertTrue((bool) $rounds->firstWhere('id', $round2->id)['ready_to_serve']);
        $this->assertTrue((bool) $rounds->firstWhere('id', $round2->id)['can_mark_served']);
    }

    public function test_broadcast_failure_does_not_fail_served_transition(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRoundWithStations();
        $this->markAllTicketsReady($order);

        Event::listen(DiningOpsSignalBroadcasted::class, function (): void {
            throw new \RuntimeException('broadcast exploded');
        });

        Sanctum::actingAs($waiter);
        $this->postJson(route('api.v1.waiter.sessions.rounds.served', [$session->id, $order->id]))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.served', true);

        $this->assertNotNull($order->fresh()->served_at);
    }

    public function test_historical_dining_round_without_served_at_remains_readable(): void
    {
        $this->enableDining();

        [$waiter, $session, $order] = $this->openDiningRoundWithStations();
        $this->assertNull($order->served_at);
        $this->assertFalse($order->isServed());

        Sanctum::actingAs($waiter);
        $this->getJson(route('api.v1.waiter.sessions.show', $session->id))
            ->assertOk()
            ->assertJsonPath('data.rounds.0.served', false)
            ->assertJsonPath('data.rounds.0.served_at', null);
    }

    /**
     * @return array{0: User, 1: DiningSession, 2: Order}
     */
    protected function openDiningRoundWithStations(): array
    {
        $waiter = User::factory()->waiter()->create();
        User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $chef = User::factory()->chef()->create();
        unset($barista, $chef);

        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, null, $waiter);

        $bar = $this->makeVariant(PreparationStation::Bar, 'Latte');
        $kitchen = $this->makeVariant(PreparationStation::Kitchen, 'Pasta');

        $order = app(OrderServiceInterface::class)->placeDiningRound($waiter, $session, [
            ['product_variant_id' => $bar->id, 'quantity' => 1],
            ['product_variant_id' => $kitchen->id, 'quantity' => 1],
        ]);
        $order = app(DiningSessionServiceInterface::class)->acceptRound($session, $order, $waiter);

        return [$waiter, $session->fresh(), $order->fresh(['preparations'])];
    }

    protected function markAllTicketsReady(Order $order): void
    {
        $barista = User::factory()->barista()->create();
        $chef = User::factory()->chef()->create();
        $prep = app(OrderPreparationServiceInterface::class);

        $order->loadMissing('preparations');
        foreach ($order->preparations as $ticket) {
            $actor = $ticket->station === PreparationStation::Kitchen ? $chef : $barista;
            $prep->transition($ticket, $actor, OrderPreparationStatus::Accepted);
            $prep->transition($ticket->fresh(), $actor, OrderPreparationStatus::Preparing);
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

        $variant = ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => '10.00',
            'is_active' => true,
            'is_available' => true,
        ]);

        return $variant->fresh('recipe');
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
