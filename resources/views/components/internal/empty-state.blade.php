@props([
    'message' => 'Nothing to show yet.',
])

<div {{ $attributes->merge(['class' => 'text-center text-muted fs-7 py-10']) }}>
    {{ $message }}
</div>
