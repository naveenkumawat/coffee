@props([
    'items' => [],
    'label' => 'Actions',
    'buttonClass' => 'btn btn-light btn-active-light-primary btn-sm',
    'menuWidthClass' => 'w-200px',
])

@php
    $actionItems = collect($items)
        ->filter(fn (array $item): bool => $item['visible'] ?? true)
        ->values();
@endphp

<div class="internal-action-dropdown">
    <a
        href="#"
        class="{{ $buttonClass }} internal-action-dropdown-trigger"
        data-kt-menu-trigger="click"
        data-kt-menu-placement="bottom-end"
    >
        <span>{{ $label }}</span>
        <i class="ki-duotone ki-down fs-5 m-0">
            <span class="path1"></span>
            <span class="path2"></span>
        </i>
    </a>

    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 py-4 {{ $menuWidthClass }}" data-kt-menu="true">
        @foreach ($actionItems as $item)
            @if (($item['type'] ?? null) === 'separator')
                <div class="separator my-2"></div>
                @continue
            @endif

            @php
                $method = strtoupper($item['method'] ?? 'GET');
                $disabled = (bool) ($item['disabled'] ?? false);
                $isDanger = (bool) ($item['danger'] ?? false);
                $linkClass = 'menu-link px-3';

                if ($disabled) {
                    $linkClass .= ' disabled pe-none opacity-50';
                }

                if ($isDanger) {
                    $linkClass .= ' text-danger';
                }
            @endphp

            <div class="menu-item px-3">
                @if ($method === 'GET')
                    <a
                        href="{{ $disabled ? '#' : ($item['url'] ?? '#') }}"
                        class="{{ $linkClass }}"
                        @if ($disabled) aria-disabled="true" tabindex="-1" @endif
                        @if (! empty($item['target'])) target="{{ $item['target'] }}" @endif
                    >
                        @if (! empty($item['icon']))
                            <i class="ki-duotone {{ $item['icon'] }} fs-6 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        @endif
                        <span>{{ $item['label'] }}</span>
                    </a>
                @elseif ($disabled)
                    <button type="button" class="{{ $linkClass }} border-0 bg-transparent w-100 text-start d-flex align-items-center" disabled aria-disabled="true">
                        @if (! empty($item['icon']))
                            <i class="ki-duotone {{ $item['icon'] }} fs-6 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        @endif
                        <span>{{ $item['label'] }}</span>
                    </button>
                @else
                    <form
                        method="POST"
                        action="{{ $item['url'] }}"
                        @if (! empty($item['confirm']))
                            onsubmit="return confirm(@js($item['confirm']))"
                        @endif
                    >
                        @csrf
                        @if ($method !== 'POST')
                            @method($method)
                        @endif
                        <button type="submit" class="{{ $linkClass }} border-0 bg-transparent w-100 text-start d-flex align-items-center">
                            @if (! empty($item['icon']))
                                <i class="ki-duotone {{ $item['icon'] }} fs-6 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                    <span class="path5"></span>
                                </i>
                            @endif
                            <span>{{ $item['label'] }}</span>
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
