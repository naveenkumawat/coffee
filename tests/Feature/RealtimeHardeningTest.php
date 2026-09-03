<?php

namespace Tests\Feature;

use App\Enums\OperationalNotificationPriority;
use App\Enums\WebsiteSettingKey;
use App\Events\Dining\DiningBillReady;
use App\Events\Dining\DiningRoundPlaced;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderPreparationStatusChanged;
use App\Events\Order\OrderStatusChanged;
use App\Events\Realtime\DiningOpsSignalBroadcasted;
use App\Events\Realtime\RealtimeConnectionProbe;
use App\Listeners\Dining\WireDiningRealtimeSignals;
use App\Listeners\OperationalNotification\WireOperationalOrderPlaced;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Providers\EventServiceProvider;
use App\Services\Dining\DiningRealtimePublisher;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\OperationalNotification\OperationalNotificationServiceInterface;
use App\Services\Realtime\RealtimeHealthService;
use App\Services\Realtime\RealtimePresenceService;
use App\Services\Realtime\RealtimePresenceServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RealtimeHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_events_register_exactly_one_of_each_intended_listener(): void
    {
        $provider = new EventServiceProvider($this->app);
        $this->assertFalse($provider->shouldDiscoverEvents());
        $this->assertStringContainsString('withEvents(discover: false)', File::get(base_path('bootstrap/app.php')));

        $cases = [
            OrderPlaced::class => 3,
            OrderStatusChanged::class => 6,
            OrderPreparationStatusChanged::class => 3,
            DiningRoundPlaced::class => 2,
            DiningBillReady::class => 3,
        ];

        foreach ($cases as $event => $expected) {
            $listeners = app('events')->getListeners($event);
            $this->assertCount(
                $expected,
                $listeners,
                $event.' has unexpected listener count (discovery may be re-enabled)',
            );
        }

        $prepNames = $this->listenerNames(OrderPreparationStatusChanged::class);
        $this->assertContains(WireDiningRealtimeSignals::class.'@handlePreparationStatusChanged', $prepNames);
        $this->assertSame(1, collect($prepNames)->filter(
            fn (string $name): bool => str_contains($name, 'WireDiningRealtimeSignals'),
        )->count());

        $orderPlacedNames = $this->listenerNames(OrderPlaced::class);
        $this->assertSame(1, collect($orderPlacedNames)->filter(
            fn (string $name): bool => $name === WireOperationalOrderPlaced::class
                || str_starts_with($name, WireOperationalOrderPlaced::class.'@'),
        )->count());
    }

    public function test_realtime_health_command_reports_config_without_secrets(): void
    {
        $this->artisan('coffee:realtime-health --json')
            ->assertSuccessful();

        $report = app(RealtimeHealthService::class)->inspect();
        $keys = collect($report['checks'])->pluck('key')->all();

        $this->assertContains('broadcasting', $keys);
        $this->assertContains('broadcast_auth', $keys);
        $this->assertContains('coffee_realtime', $keys);
        $this->assertArrayNotHasKey('secret', $report['config']);
        $encoded = json_encode($report);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('REVERB_APP_SECRET', $encoded);
    }

    public function test_realtime_health_probe_failure_is_isolated(): void
    {
        Event::listen(RealtimeConnectionProbe::class, function (): void {
            throw new \RuntimeException('probe exploded');
        });

        $result = app(RealtimeHealthService::class)->dispatchProbe(User::factory()->customer()->create());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Probe dispatch failed', $result['detail']);
    }

    public function test_dining_broadcast_failure_does_not_block_session_open(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            ['value' => '1'],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::OrderingManualClosed->value],
            ['value' => '0'],
        );

        Event::listen(DiningOpsSignalBroadcasted::class, function (): void {
            throw new \RuntimeException('dining signal exploded');
        });

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, null, $waiter);

        $this->assertInstanceOf(DiningSession::class, $session);
        $this->assertDatabaseHas('dining_sessions', ['id' => $session->id]);
    }

    public function test_presence_ttl_prunes_stale_unique_users_and_duplicate_heartbeats_do_not_inflate(): void
    {
        $presence = app(RealtimePresenceServiceInterface::class);
        $barista = User::factory()->barista()->create();

        $presence->heartbeat($barista);
        $presence->heartbeat($barista);
        $this->assertSame(1, $presence->summaryCounts()['barista']);

        $this->forceStalePresence($barista->id);
        $this->assertSame(0, $presence->summaryCounts()['barista']);
    }

    public function test_metrics_are_computed_not_persisted_as_duration_columns(): void
    {
        $operator = User::factory()->operator()->create();
        $service = app(OperationalNotificationServiceInterface::class);

        $notification = $service->createAndBroadcast(
            type: 'ops.hardening-metrics',
            category: 'system',
            title: 'Attention',
            message: 'Check order',
            audience: [$operator],
            priority: OperationalNotificationPriority::Normal,
            actionRequired: true,
            broadcast: false,
        );

        $recipient = $notification->recipients()->where('user_id', $operator->id)->firstOrFail();
        $service->markDelivered($recipient);
        $fresh = $recipient->fresh();

        $metrics = $service->metricsForRecipient($fresh);
        $this->assertArrayHasKey('delivery_delay_seconds', $metrics);
        $this->assertArrayHasKey('first_seen_delay_seconds', $metrics);
        $this->assertArrayNotHasKey('delivery_delay_seconds', $fresh->getAttributes());
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNull($fresh->first_seen_at);
    }

    public function test_clients_expose_diagnostics_and_reconnect_soft_reload_helpers(): void
    {
        $blade = File::get(base_path('resources/js/realtime.js'));
        $presence = File::get(base_path('resources/js/realtime/presence.js'));
        $pwa = File::get(base_path('customer-pwa/src/realtime/useRealtimeBootstrap.ts'));
        $diag = File::get(base_path('customer-pwa/src/realtime/diagnostics.ts'));

        $this->assertStringContainsString('__COFFEE_REALTIME_DIAGNOSTICS__', $blade);
        $this->assertStringContainsString('shouldSoftReloadOnReconnect', $presence);
        $this->assertStringContainsString('preparations', $presence);
        $this->assertStringContainsString('publishRealtimeDiagnostics', $pwa);
        $this->assertStringContainsString('last_reconcile_at', $diag);
        $this->assertStringContainsString('reconnect_attempts', $diag);
    }

    public function test_dining_publisher_safe_payload_has_no_sensitive_keys(): void
    {
        Event::fake([DiningOpsSignalBroadcasted::class]);

        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            ['value' => '1'],
        );
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::OrderingManualClosed->value],
            ['value' => '0'],
        );

        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);
        $session = app(DiningSessionServiceInterface::class)->startSession($table, null, $waiter);

        Event::fake([DiningOpsSignalBroadcasted::class]);
        app(DiningRealtimePublisher::class)->sessionOpened($session);

        Event::assertDispatched(DiningOpsSignalBroadcasted::class, function (DiningOpsSignalBroadcasted $event): bool {
            $payload = $event->payload;
            foreach (['email', 'phone', 'purchase_cost', 'recipe', 'payment_proof_path', 'customer_name'] as $forbidden) {
                if (array_key_exists($forbidden, $payload)) {
                    return false;
                }
            }

            return isset($payload['event_id'], $payload['type'], $payload['session_id'], $payload['updated_at']);
        });
    }

    /**
     * @return list<string>
     */
    protected function listenerNames(string $event): array
    {
        return collect(app('events')->getListeners($event))
            ->map(function ($listener): string {
                $ref = new \ReflectionFunction($listener);
                $static = $ref->getStaticVariables();
                if (isset($static['listener']) && is_array($static['listener'])) {
                    return implode('@', $static['listener']);
                }
                if (isset($static['listener']) && is_string($static['listener'])) {
                    return $static['listener'];
                }

                return 'unknown';
            })
            ->values()
            ->all();
    }

    protected function forceStalePresence(int $userId): void
    {
        $presence = app(RealtimePresenceService::class);
        $ref = new \ReflectionClass($presence);
        $roleKey = $ref->getMethod('roleCacheKey');
        $roleKey->setAccessible(true);
        $userKey = $ref->getMethod('userCacheKey');
        $userKey->setAccessible(true);

        $stale = time() - RealtimePresenceService::TTL_SECONDS - 5;
        cache()->put($userKey->invoke($presence, $userId), [
            'role' => 'barista',
            'seen_at' => $stale,
        ], now()->addMinutes(5));
        cache()->put($roleKey->invoke($presence, 'barista'), [
            $userId => $stale,
        ], now()->addMinutes(5));
    }
}
