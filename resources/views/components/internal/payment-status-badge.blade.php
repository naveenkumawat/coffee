@props([
    'status',
])

@php
    /** @var \App\Enums\PaymentStatus|null $status */
    $label = $status?->label() ?? '—';
    $badgeClass = $status?->badgeClass() ?? 'badge-light';
@endphp

<x-internal.status-badge :label="$label" :badge-class="$badgeClass" {{ $attributes }} />
