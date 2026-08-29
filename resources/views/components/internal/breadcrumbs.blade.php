@props(['items' => []])

<ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-2 internal-breadcrumbs min-w-0 flex-wrap row-gap-2">
    @foreach ($items as $item)
        <li class="breadcrumb-item {{ $loop->last ? 'text-muted' : 'text-gray-700 fw-bold lh-1' }} min-w-0">
            @if (! empty($item['url']) && ! $loop->last)
                <a href="{{ $item['url'] }}" class="text-gray-700 text-hover-primary text-break">{{ $item['label'] }}</a>
            @else
                <span class="text-break">{{ $item['label'] }}</span>
            @endif
        </li>

        @if (! $loop->last)
            <li class="breadcrumb-item flex-shrink-0">
                <i class="ki-outline ki-right fs-7 text-gray-700 mx-n1"></i>
            </li>
        @endif
    @endforeach
</ul>
