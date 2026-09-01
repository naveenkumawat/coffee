@props([
    'items' => [],
    'label' => 'Invoice',
    'menuWidthClass' => 'w-225px',
])

@php
    $visibleItems = collect($items)
        ->filter(fn (array $item): bool => $item['visible'] ?? true)
        ->values()
        ->all();
@endphp

@if ($visibleItems !== [])
    <x-internal.action-dropdown
        :label="$label"
        button-class="btn btn-light-dark btn-active-light-dark btn-sm internal-button internal-action-dropdown-trigger"
        :menu-width-class="$menuWidthClass"
        :items="$visibleItems"
        {{ $attributes }}
    />
@endif
