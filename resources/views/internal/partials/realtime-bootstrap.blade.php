@php
    $realtimeUser = auth('admin')->user() ?? auth('web')->user();
    $realtimeEnabled = config('coffee.realtime.enabled')
        && filled(config('coffee.realtime.key'))
        && $realtimeUser !== null;

    $roleChannel = match ($panel ?? null) {
        'administrator' => 'role.administrator',
        'operator' => 'role.operator',
        'barista' => 'role.barista',
        'chef' => 'role.chef',
        'waiter' => 'role.waiter',
        default => null,
    };

    $showPresenceSummary = in_array($panel ?? null, ['administrator', 'operator'], true);

    $realtimeConfig = [
        'enabled' => $realtimeEnabled,
        'key' => config('coffee.realtime.key'),
        'host' => config('coffee.realtime.host'),
        'port' => config('coffee.realtime.port'),
        'scheme' => config('coffee.realtime.scheme'),
        'authEndpoint' => url('/broadcasting/auth'),
        'csrfToken' => csrf_token(),
        'userId' => $realtimeUser?->id,
        'roleChannel' => $roleChannel,
        'joinPresence' => $realtimeEnabled && $roleChannel !== null,
        'showPresenceSummary' => $showPresenceSummary,
        'presenceApiBase' => url('/api/v1/realtime/presence'),
        'presenceIntervalMs' => 20000,
    ];
@endphp

@if ($realtimeUser !== null)
    <script>
        window.__COFFEE_REALTIME__ = @json($realtimeConfig);
        window.__COFFEE_OPS_NOTIFICATIONS__ = {
            reminderIntervalMs: 30000,
            apiBase: @json(url('/api/v1/notifications')),
        };
    </script>
    @vite(['resources/js/notifications.js'])
@endif

@if ($realtimeEnabled)
    <div
        id="coffee-realtime-indicator"
        class="coffee-realtime-indicator"
        data-state="connecting"
        hidden
        role="status"
        aria-live="polite"
    >Connecting…</div>

    @vite(['resources/js/realtime.js'])
@endif
