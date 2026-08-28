@props([
    'label',
    'value',
    'icon' => 'ki-abstract-26',
    'description' => null,
    'color' => 'primary',
])

<div class="card card-flush h-md-100">
    <div class="card-header pt-5">
        <div class="card-title d-flex flex-column">
            <div class="d-flex align-items-center gap-3">
                <span class="symbol symbol-45px">
                    <span class="symbol-label bg-light-{{ $color }}">
                        <i class="ki-outline {{ $icon }} fs-2 text-{{ $color }}"></i>
                    </span>
                </span>
                <div>
                    <span class="fs-2hx fw-bold text-gray-900 me-2 lh-1 ls-n2">{{ $value }}</span>
                    <span class="text-gray-500 pt-1 fw-semibold fs-6 d-block">{{ $label }}</span>
                </div>
            </div>
        </div>
    </div>

    @if ($description)
        <div class="card-body pt-2">
            <span class="text-gray-600 fw-semibold">{{ $description }}</span>
        </div>
    @endif
</div>
