@props(['items' => []])

<ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
    @foreach ($items as $item)
        <li class="breadcrumb-item {{ $loop->last ? 'text-muted' : 'text-gray-700 fw-bold lh-1' }}">
            @if (! empty($item['url']) && ! $loop->last)
                <a href="{{ $item['url'] }}" class="text-gray-700 text-hover-primary">{{ $item['label'] }}</a>
            @else
                {{ $item['label'] }}
            @endif
        </li>

        @if (! $loop->last)
            <li class="breadcrumb-item">
                <i class="ki-outline ki-right fs-7 text-gray-700 mx-n1"></i>
            </li>
        @endif
    @endforeach
</ul>
