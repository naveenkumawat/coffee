@extends('barista.layouts.default')

@section('page-title', $recipe->variant?->product?->name.' - '.$recipe->variant?->name)

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Recipes', 'url' => route('barista.recipes.index')],
        ['label' => $recipe->variant?->product?->name.' - '.$recipe->variant?->name],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('barista.recipes.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-5">
        <div class="col-xl-4">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Preparation Notes</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="d-flex flex-column gap-5">
                        <div>
                            <div class="text-muted fs-7 mb-1">Product</div>
                            <div class="fw-bold text-gray-900">{{ $recipe->variant?->product?->name }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Variant</div>
                            <div class="text-gray-700">{{ $recipe->variant?->name }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Instructions</div>
                            <div class="text-gray-700">{{ $recipe->preparation_notes ?: 'No preparation notes provided.' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Ingredient Quantities</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive internal-table-wrapper">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th>Ingredient</th>
                                    <th>Quantity</th>
                                    <th>Base Quantity</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-600">
                                @forelse ($recipe->lines as $line)
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
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-10">No recipe lines configured yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
