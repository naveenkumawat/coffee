<?php

namespace App\Services\Realtime;

use App\Events\Realtime\RealtimeConnectionProbe;
use App\Models\OperationalNotificationRecipient;
use App\Models\User;
use App\Services\OperationalNotification\OperationalNotificationServiceInterface;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * R1.7 read-only realtime health diagnostics for ops/runbooks.
 * Never used as a gate for business APIs.
 */
class RealtimeHealthService
{
    public function __construct(
        protected OperationalNotificationServiceInterface $notifications,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     checks: list<array{key: string, ok: bool, detail: string}>,
     *     config: array<string, mixed>
     * }
     */
    public function inspect(): array
    {
        $checks = [];

        $checks[] = [
            'key' => 'app',
            'ok' => true,
            'detail' => sprintf('env=%s debug=%s', (string) config('app.env'), config('app.debug') ? 'on' : 'off'),
        ];

        $broadcast = (string) config('broadcasting.default');
        $checks[] = [
            'key' => 'broadcasting',
            'ok' => in_array($broadcast, ['reverb', 'log', 'null', 'pusher', 'ably', 'redis'], true),
            'detail' => 'BROADCAST_CONNECTION='.$broadcast,
        ];

        $realtimeEnabled = (bool) config('coffee.realtime.enabled');
        $keyFilled = filled(config('coffee.realtime.key'));
        $checks[] = [
            'key' => 'coffee_realtime',
            'ok' => $realtimeEnabled ? $keyFilled : true,
            'detail' => sprintf(
                'enabled=%s key=%s host=%s port=%s scheme=%s',
                $realtimeEnabled ? 'yes' : 'no',
                $keyFilled ? 'set' : 'missing',
                (string) config('coffee.realtime.host'),
                (string) config('coffee.realtime.port'),
                (string) config('coffee.realtime.scheme'),
            ),
        ];

        $reverbKey = filled(config('reverb.apps.apps.0.key'));
        $checks[] = [
            'key' => 'reverb_credentials',
            'ok' => $broadcast !== 'reverb' || ($keyFilled && $reverbKey),
            'detail' => $broadcast === 'reverb'
                ? ($keyFilled ? 'REVERB_* present for client connection' : 'REVERB_APP_KEY missing while broadcast=reverb')
                : 'Reverb not the active broadcaster (REST still authoritative)',
        ];

        $authRoute = Route::has('broadcasting.auth') || $this->broadcastAuthPathExists();
        $checks[] = [
            'key' => 'broadcast_auth',
            'ok' => $authRoute,
            'detail' => $authRoute ? '/broadcasting/auth registered' : 'broadcast auth route missing',
        ];

        $presence = app(RealtimePresenceServiceInterface::class)->summaryCounts();
        $checks[] = [
            'key' => 'presence_cache',
            'ok' => true,
            'detail' => 'advisory counts '.json_encode($presence),
        ];

        $ok = collect($checks)->every(static fn (array $check): bool => $check['ok'] === true);

        return [
            'ok' => $ok,
            'checks' => $checks,
            'config' => [
                'broadcast_connection' => $broadcast,
                'realtime_enabled' => $realtimeEnabled,
                'realtime_host' => config('coffee.realtime.host'),
                'realtime_port' => config('coffee.realtime.port'),
                'realtime_scheme' => config('coffee.realtime.scheme'),
                'queue_connection' => config('queue.default'),
                'cache_store' => config('cache.default'),
            ],
        ];
    }

    /**
     * Attempt a tiny probe broadcast. Failures are reported, never thrown to callers.
     *
     * @return array{ok: bool, detail: string, probe_id: string|null}
     */
    public function dispatchProbe(?User $user = null): array
    {
        try {
            $user ??= User::query()->where('is_active', true)->orderBy('id')->first();
            if ($user === null) {
                return [
                    'ok' => false,
                    'detail' => 'No active user available for probe dispatch',
                    'probe_id' => null,
                ];
            }

            $event = RealtimeConnectionProbe::forUser($user);
            event($event);

            return [
                'ok' => true,
                'detail' => 'Probe event dispatched (delivery depends on Reverb/process)',
                'probe_id' => $event->probeId,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'detail' => 'Probe dispatch failed: '.$exception->getMessage(),
                'probe_id' => null,
            ];
        }
    }

    /**
     * Sample computed delay metrics for recent recipients (not persisted durations).
     *
     * @return list<array<string, mixed>>
     */
    public function recentRecipientDelaySamples(int $limit = 5): array
    {
        $recipients = OperationalNotificationRecipient::query()
            ->with('notification')
            ->latest('id')
            ->limit(max(1, min($limit, 25)))
            ->get();

        return $recipients->map(function (OperationalNotificationRecipient $recipient): array {
            $notification = $recipient->notification;
            $metrics = $this->notifications->metricsForRecipient($recipient);

            return [
                'recipient_id' => (int) $recipient->getKey(),
                'type' => $notification?->type,
                'action_required' => (bool) ($notification?->action_required),
                'resolved' => $notification?->resolved_at !== null,
                'reminder_count' => (int) $recipient->reminder_count,
                'broadcast_at' => $recipient->broadcast_at?->toIso8601String(),
                'delivered_at' => $recipient->delivered_at?->toIso8601String(),
                'first_seen_at' => $recipient->first_seen_at?->toIso8601String(),
                'acknowledged_at' => $recipient->acknowledged_at?->toIso8601String(),
                'last_reminded_at' => $recipient->last_reminded_at?->toIso8601String(),
                'delays' => $metrics,
            ];
        })->all();
    }

    protected function broadcastAuthPathExists(): bool
    {
        foreach (Route::getRoutes() as $route) {
            if (in_array('POST', $route->methods(), true) && trim($route->uri(), '/') === 'broadcasting/auth') {
                return true;
            }
        }

        return false;
    }
}
