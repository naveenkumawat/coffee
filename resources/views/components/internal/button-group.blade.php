@props([
    'items' => [],
    'size' => 'sm',
    'justify' => 'end',
    'wrap' => true,
])

@php
    $groupItems = collect($items)
        ->filter(fn (array $item): bool => $item['visible'] ?? true)
        ->values();
@endphp

@if ($groupItems->isNotEmpty())
    <div class="internal-button-group-wrapper justify-content-{{ $justify }}">
        <div class="btn-group btn-group-{{ $size }} internal-button-group {{ $wrap ? 'internal-button-group-wrap' : '' }}" role="group">
            @foreach ($groupItems as $item)
                <x-internal.button
                    :label="$item['label']"
                    :url="$item['url'] ?? null"
                    :type="$item['type'] ?? 'button'"
                    :variant="$item['variant'] ?? 'default'"
                    :icon="$item['icon'] ?? null"
                    :disabled="(bool) ($item['disabled'] ?? false)"
                    :target="$item['target'] ?? null"
                    class="internal-button-group-button"
                />
            @endforeach
        </div>
    </div>
@endif
