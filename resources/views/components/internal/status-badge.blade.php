@props([
    'label',
    'badgeClass' => 'badge-light',
])

<span {{ $attributes->merge(['class' => 'badge '.$badgeClass]) }}>
    {{ $label }}
</span>
