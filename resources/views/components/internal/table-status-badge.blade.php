@props([
    'state',
])

@php
    $status = \App\Enums\TableOperationalStatus::fromState((string) $state);
@endphp

<x-internal.status-badge :label="$status->label()" :badge-class="$status->badgeClass()" {{ $attributes }} />
