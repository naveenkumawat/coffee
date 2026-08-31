@props([
    'status',
    'order' => null,
])

@php
    $label = $order?->customerLabelForStatus($status) ?: $status->label();
@endphp

<span class="badge {{ $status->badgeClass() }}">
    {{ $label }}
</span>
