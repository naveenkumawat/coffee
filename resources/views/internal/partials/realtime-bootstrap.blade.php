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
    ];
@endphp

@if ($realtimeEnabled)
    <div
        id="coffee-realtime-indicator"
        class="coffee-realtime-indicator"
        data-state="connecting"
        hidden
        role="status"
        aria-live="polite"
    >Connecting…</div>

    <script>
        window.__COFFEE_REALTIME__ = @json($realtimeConfig);
    </script>
    @vite(['resources/js/realtime.js'])
@endif
