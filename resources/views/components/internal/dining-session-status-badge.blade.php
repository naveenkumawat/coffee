@props([
    'status',
])

@php
    /** @var \App\Enums\DiningSessionStatus|null $status */
    $label = $status?->label() ?? 'Unknown';
    $badgeClass = $status?->badgeClass() ?? 'badge-light';
@endphp

<x-internal.status-badge :label="$label" :badge-class="$badgeClass" {{ $attributes }} />
