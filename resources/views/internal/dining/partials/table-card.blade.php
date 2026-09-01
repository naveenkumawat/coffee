@php
    $table = $row['table'];
    $state = (string) $row['state'];
    $session = $row['session'] ?? null;
@endphp

<div class="card card-flush internal-card h-100">
    <div class="card-body d-flex flex-column">
        <div class="fw-bold fs-4 text-gray-900 mb-2">{{ $table->displayLabel() }}</div>
        <div class="mb-4">
            <x-internal.table-status-badge :state="$state" />
        </div>
        <div class="mt-auto">
            @if ($session)
                <x-internal.button
                    label="Open session"
                    :url="route($sessionShowRoute, $session)"
                    variant="default"
                    icon="ki-eye"
                />
            @elseif ($state === 'available' && $table->is_active)
                <form method="POST" action="{{ $startSessionRoute }}">
                    @csrf
                    <input type="hidden" name="cafe_table_id" value="{{ $table->getKey() }}">
                    <x-internal.button label="Start session" type="submit" variant="success" icon="ki-plus" />
                </form>
            @else
                <span class="text-muted fs-7">No action available</span>
            @endif
        </div>
    </div>
</div>
