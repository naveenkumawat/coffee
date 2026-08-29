@props([
    'label' => null,
    'url' => null,
    'type' => 'button',
    'variant' => 'default',
    'icon' => null,
    'disabled' => false,
    'stretch' => false,
    'iconSize' => 'fs-6',
])

@php
    $variantClasses = [
        'default' => 'btn btn-light btn-active-light-primary',
        'success' => 'btn btn-light-success btn-active-light-success',
        'dark' => 'btn btn-light-dark btn-active-light-dark',
        'danger' => 'btn btn-light-danger btn-active-light-danger',
    ];

    $classes = trim(($variantClasses[$variant] ?? $variantClasses['default']).' internal-button '.($stretch ? 'w-100' : ''));
@endphp

@if ($url)
    <a
        href="{{ $disabled ? '#' : $url }}"
        {{ $attributes->merge(['class' => $classes]) }}
        @if ($disabled) aria-disabled="true" tabindex="-1" @endif
    >
        @if ($icon)
            <i class="ki-duotone {{ $icon }} {{ $iconSize }}">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
            </i>
        @endif
        <span>{{ $label ?? $slot }}</span>
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->merge(['class' => $classes]) }}
        @if ($disabled) disabled aria-disabled="true" @endif
    >
        @if ($icon)
            <i class="ki-duotone {{ $icon }} {{ $iconSize }}">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
            </i>
        @endif
        <span>{{ $label ?? $slot }}</span>
    </button>
@endif
