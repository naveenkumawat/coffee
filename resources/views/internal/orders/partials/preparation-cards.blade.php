@props([
    'items',
    'emptyMessage' => 'No recipe preparation details are available yet.',
])

<div class="d-flex flex-column gap-5">
    @php($itemsWithRecipes = collect($items)->filter(fn ($item) => $item->recipe))

    @forelse ($itemsWithRecipes as $item)
        <div class="border border-gray-200 rounded-3 p-5">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 mb-4">
                <div>
                    <h4 class="fw-bold text-gray-900 mb-1">{{ $item->product_name }} - {{ $item->variant_name }}</h4>
                    <div class="text-muted fs-7">Quantity: {{ $item->quantity }}</div>
                </div>
                <div class="badge badge-light-primary align-self-start">
                    Recipe Ready
                </div>
            </div>

            <div class="mb-4">
                <div class="text-muted fs-7 mb-1">Preparation Notes</div>
                <div class="text-gray-700">{{ $item->recipe->preparation_notes ?: 'No preparation notes provided.' }}</div>
            </div>

            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-7 gy-3 internal-table mb-0">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                            <th>Ingredient</th>
                            <th>Quantity</th>
                            <th>Base Qty</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @foreach ($item->recipe->lines as $line)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $line->ingredient?->name }}</span>
                                        <span class="text-muted">{{ $line->ingredient?->brand?->name ?: 'No brand assigned' }}</span>
                                    </div>
                                </td>
                                <td>{{ number_format((float) $line->quantity, 3) }} {{ $line->measurement_unit->value }}</td>
                                <td>{{ number_format((float) $line->base_quantity, 3) }} {{ $line->base_measurement_unit->value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-6">
            <div class="d-flex flex-stack flex-grow-1">
                <div class="fw-semibold">
                    <h4 class="text-gray-900 fw-bold mb-1">Preparation details pending</h4>
                    <span class="fs-6 text-gray-700">{{ $emptyMessage }}</span>
                </div>
            </div>
        </div>
    @endforelse
</div>
