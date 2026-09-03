<?php

namespace Tests\Feature;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderPreparationStatus;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Events\Realtime\DiningOpsSignalBroadcasted;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiningRealtimeScopedChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_owner_customer_can_authorize_dining_session_channel(): void
    {
        $this->enableDining();

        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, $customer);

        $this->actingAs($customer, 'web')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-dining-session.'.$session->id,
                'socket_id' => '1234.5678',
            ])
            ->assertOk();
    }

    public function test_other_customer_cannot_authorize_dining_session_channel(): void
    {
        $this->enableDining();

        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, $owner);

        $this->actingAs($other, 'web')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-dining-session.'.$session->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_waiter_can_authorize_session_and_table_channels(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, null, $waiter);

        $this->actingAs($waiter, 'admin')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-dining-session.'.$session->id,
                'socket_id' => '1234.5678',
            ])
            ->assertOk();

        $this->actingAs($waiter, 'admin')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-table.'.$table->id,
                'socket_id' => '1234.5678',
            ])
            ->assertOk();
    }

    public function test_customer_and_guest_cannot_authorize_table_channel(): void
    {
        $table = CafeTable::factory()->create(['is_active' => true]);
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'web')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-table.'.$table->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();

        $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-table.'.$table->id,
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    }

    public function test_walk_in_session_denies_unrelated_customer_channel_access(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $customer = User::factory()->customer()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, null, $waiter);

        $this->assertNull($session->customer_id);

        $this->actingAs($customer, 'web')
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-dining-session.'.$session->id,
                'socket_id' => '1234.5678',
            ])
            ->assertForbidden();
    }

    public function test_session_open_round_bill_payment_and_close_emit_safe_dining_ops_signals(): void
    {
        $this->enableDining();
        Event::fake([DiningOpsSignalBroadcasted::class]);

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant();

        Sanctum::actingAs($waiter);

        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->assertCreated()->json('data.id');

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event) use ($sessionId, $table): bool {
            return $this->assertSafeDiningPayload($event, 'session.opened', $sessionId, (int) $table->id);
        });

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'r16-round-1',
        ])->assertCreated();

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event) use ($sessionId): bool {
            return ($event->payload['type'] ?? null) === 'round.placed'
                && (int) ($event->payload['session_id'] ?? 0) === $sessionId
                && isset($event->payload['order_id']);
        });

        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::AwaitingPayment->value);

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event) use ($sessionId): bool {
            return ($event->payload['type'] ?? null) === 'bill.requested'
                && (int) ($event->payload['session_id'] ?? 0) === $sessionId;
        });

        $this->postJson(route('api.v1.waiter.sessions.payment-method', $sessionId), [
            'payment_method' => 'cash',
        ])->assertOk();

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event) use ($sessionId): bool {
            return ($event->payload['type'] ?? null) === 'payment.changed'
                && (int) ($event->payload['session_id'] ?? 0) === $sessionId
                && str_contains((string) ($event->payload['state'] ?? ''), 'method_');
        });

        $this->postJson(route('api.v1.waiter.sessions.cash.receive', $sessionId))->assertOk();

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event) use ($sessionId): bool {
            return ($event->payload['type'] ?? null) === 'payment.changed'
                && (int) ($event->payload['session_id'] ?? 0) === $sessionId
                && ($event->payload['state'] ?? null) === 'confirmed';
        });

        $this->postJson(route('api.v1.waiter.sessions.close', $sessionId))->assertOk();

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event) use ($sessionId): bool {
            return ($event->payload['type'] ?? null) === 'session.closed'
                && (int) ($event->payload['session_id'] ?? 0) === $sessionId;
        });

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event) use ($sessionId, $table): bool {
            return ($event->payload['type'] ?? null) === 'table.released'
                && (int) ($event->payload['session_id'] ?? 0) === $sessionId
                && (int) ($event->payload['table_id'] ?? 0) === (int) $table->id;
        });

        $session = DiningSession::query()->find($sessionId);
        $this->assertSame(DiningSessionStatus::Closed, $session?->status);
    }

    public function test_station_progress_emits_preparation_and_all_ready_signals_for_dining_round(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $barista = User::factory()->barista()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant();

        Sanctum::actingAs($waiter);
        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $orderId = (int) $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'r16-prep',
        ])->assertCreated()->json('data.orders.0.id');

        $session = DiningSession::query()->with('orders.preparations')->findOrFail($sessionId);
        $order = $session->orders->firstWhere('id', $orderId) ?? $session->orders->first();
        $this->assertNotNull($order);
        $ticket = $order->preparations->first();
        $this->assertNotNull($ticket);

        Event::fake([DiningOpsSignalBroadcasted::class]);

        $preparations = app(OrderPreparationServiceInterface::class);
        $preparations->transition($ticket, $barista, OrderPreparationStatus::Accepted);
        $preparations->transition($ticket->fresh(), $barista, OrderPreparationStatus::Preparing);
        $preparations->transition($ticket->fresh(), $barista, OrderPreparationStatus::Ready);

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event) use ($sessionId): bool {
            return ($event->payload['type'] ?? null) === 'preparation.progress'
                && (int) ($event->payload['session_id'] ?? 0) === $sessionId;
        });

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event) use ($sessionId): bool {
            return ($event->payload['type'] ?? null) === 'round.all_stations_ready'
                && (int) ($event->payload['session_id'] ?? 0) === $sessionId;
        });
    }

    public function test_duplicate_bill_request_does_not_emit_second_bill_signal(): void
    {
        $this->enableDining();

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant();

        Sanctum::actingAs($waiter);

        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'r16-bill-dedupe',
        ])->assertCreated();

        Event::fake([DiningOpsSignalBroadcasted::class]);

        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId))->assertOk();

        $firstBillSignals = collect(Event::dispatched(DiningOpsSignalBroadcasted::class))
            ->filter(fn (array $args): bool => ($args[0]->payload['type'] ?? null) === 'bill.requested')
            ->count();
        $this->assertSame(1, $firstBillSignals);

        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::AwaitingPayment->value)
            ->assertJsonPath('data.capabilities.can_request_bill', false);

        $billSignals = collect(Event::dispatched(DiningOpsSignalBroadcasted::class))
            ->filter(fn (array $args): bool => ($args[0]->payload['type'] ?? null) === 'bill.requested')
            ->count();
        $this->assertSame(1, $billSignals);
    }

    public function test_multi_waiter_second_close_returns_canonical_state_not_generic_failure(): void
    {
        $this->enableDining();

        $waiterA = User::factory()->waiter()->create();
        $waiterB = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $variant = $this->makePurchasableVariant();

        Sanctum::actingAs($waiterA);
        $sessionId = (int) $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])->json('data.id');

        $this->postJson(route('api.v1.waiter.sessions.drafts.store', $sessionId), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.rounds.store', $sessionId), [
            'idempotency_key' => 'r16-multi-close',
        ])->assertCreated();
        $this->postJson(route('api.v1.waiter.sessions.request-bill', $sessionId))->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.payment-method', $sessionId), [
            'payment_method' => 'cash',
        ])->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.cash.receive', $sessionId))->assertOk();
        $this->postJson(route('api.v1.waiter.sessions.close', $sessionId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningSessionStatus::Closed->value);

        Sanctum::actingAs($waiterB);
        $response = $this->postJson(route('api.v1.waiter.sessions.close', $sessionId));

        $this->assertTrue(in_array($response->status(), [200, 404, 422], true));
        if ($response->status() === 200) {
            $response->assertJsonPath('data.status', DiningSessionStatus::Closed->value);
        }
        if ($response->status() === 422) {
            $this->assertNotEmpty($response->json('message') ?? $response->json('errors'));
        }
    }

    public function test_broadcast_listener_failure_does_not_fail_session_open(): void
    {
        $this->enableDining();

        Event::listen(DiningOpsSignalBroadcasted::class, function (): void {
            throw new \RuntimeException('broadcast exploded');
        });

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);

        Sanctum::actingAs($waiter);

        $this->postJson(route('api.v1.waiter.sessions.store'), [
            'cafe_table_id' => $table->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', DiningSessionStatus::Open->value);
    }

    public function test_pwa_and_blade_listen_for_dining_ops_signals(): void
    {
        $connection = file_get_contents(base_path('customer-pwa/src/realtime/RealtimeConnection.ts'));
        $tablesPage = file_get_contents(base_path('customer-pwa/src/pages/waiter/WaiterTablesPage.tsx'));
        $sessionPage = file_get_contents(base_path('customer-pwa/src/pages/waiter/WaiterSessionPage.tsx'));
        $customerDining = file_get_contents(base_path('customer-pwa/src/pages/DiningSessionPage.tsx'));
        $blade = file_get_contents(base_path('resources/js/realtime.js'));
        $channels = file_get_contents(base_path('routes/channels.php'));

        $this->assertStringContainsString('.dining.ops', $connection);
        $this->assertStringContainsString('subscribeDiningSession', $connection);
        $this->assertStringContainsString('useDiningOpsSync', $tablesPage);
        $this->assertStringContainsString('useDiningOpsSync', $sessionPage);
        $this->assertStringContainsString('useDiningOpsSync', $customerDining);
        $this->assertStringContainsString('.dining.ops', $blade);
        $this->assertStringContainsString("Broadcast::channel('dining-session.{sessionId}'", $channels);
        $this->assertStringContainsString("Broadcast::channel('table.{tableId}'", $channels);
    }

    protected function assertSafeDiningPayload(
        DiningOpsSignalBroadcasted $event,
        string $type,
        int $sessionId,
        int $tableId,
    ): bool {
        $payload = $event->payload;
        $forbidden = [
            'purchase_cost',
            'cost_per_unit',
            'margin',
            'profit',
            'email',
            'phone',
            'payment_proof_path',
            'recipe',
            'customer_name',
            'customer_phone',
        ];

        foreach ($forbidden as $key) {
            if (array_key_exists($key, $payload)) {
                return false;
            }
        }

        $channels = collect($event->broadcastOn())->map(fn ($channel) => (string) $channel)->all();

        return ($payload['type'] ?? null) === $type
            && (int) ($payload['session_id'] ?? 0) === $sessionId
            && (int) ($payload['table_id'] ?? 0) === $tableId
            && isset($payload['event_id'], $payload['updated_at'])
            && in_array('private-dining-session.'.$sessionId, $channels, true)
            && in_array('private-table.'.$tableId, $channels, true)
            && in_array('private-role.waiter', $channels, true)
            && $event->broadcastAs() === 'dining.ops';
    }

    protected function enableDining(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            ['value' => '1'],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::OrderingManualClosed->value],
            ['value' => '0'],
        );
    }

    protected function makePurchasableVariant(): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'price' => '7.50',
            'is_active' => true,
            'is_available' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);
    }
}
