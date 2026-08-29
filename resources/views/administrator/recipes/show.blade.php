@extends('administrator.layouts.default')

@section('page-title', $recipe->variant?->product?->name.' - '.$recipe->variant?->name)

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Recipes', 'url' => route('administrator.recipes.index')],
        ['label' => $recipe->variant?->product?->name.' - '.$recipe->variant?->name],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.recipes.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'Edit', 'url' => route('administrator.recipes.edit', $recipe), 'variant' => 'success', 'icon' => 'ki-notepad-edit'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-5">
        <div class="col-xl-4">
            <div class="card card-flush internal-card h-xl-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Recipe Overview</h3>
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
                            <div class="text-muted fs-7 mb-1">Category</div>
                            <div class="text-gray-700">{{ $recipe->variant?->product?->category?->name ?: 'Uncategorized' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Status</div>
                            <span class="badge {{ $recipe->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                {{ $recipe->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Preparation Notes</div>
                            <div class="text-gray-700">{{ $recipe->preparation_notes ?: 'No preparation notes provided.' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card card-flush internal-card h-xl-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Variant Costing Summary</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="row g-5">
                        <div class="col-md-3">
                            <div class="notice d-flex bg-light-success rounded border-success border border-dashed p-5 h-100">
                                <div class="d-flex flex-column">
                                    <span class="text-muted fs-7">Production Cost</span>
                                    <span class="fw-bold text-gray-900 mt-2">Rs {{ number_format((float) $costing['production_cost'], 4) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5 h-100">
                                <div class="d-flex flex-column">
                                    <span class="text-muted fs-7">Selling Price</span>
                                    <span class="fw-bold text-gray-900 mt-2">Rs {{ number_format((float) $costing['selling_price'], 4) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-5 h-100">
                                <div class="d-flex flex-column">
                                    <span class="text-muted fs-7">Gross Profit</span>
                                    <span class="fw-bold text-gray-900 mt-2">Rs {{ number_format((float) $costing['gross_profit'], 4) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-5 h-100">
                                <div class="d-flex flex-column">
                                    <span class="text-muted fs-7">Margin</span>
                                    <span class="fw-bold text-gray-900 mt-2">{{ $costing['margin_percentage'] }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-header pt-7">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Recipe Lines</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Ingredient</th>
                            <th>Input Quantity</th>
                            <th>Base Quantity</th>
                            <th>Unit Cost</th>
                            <th>Line Cost</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($costing['lines'] as $line)
                            <tr>
                                <td>{{ $line['ingredient_name'] }}</td>
                                <td>{{ number_format((float) $line['quantity'], 3) }} {{ $line['measurement_unit'] }}</td>
                                <td>{{ number_format((float) $line['base_quantity'], 3) }} {{ $line['base_measurement_unit'] }}</td>
                                <td>Rs {{ number_format((float) $line['ingredient_cost_per_unit'], 4) }}/{{ $line['ingredient_base_unit'] }}</td>
                                <td>Rs {{ number_format((float) $line['line_cost'], 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No recipe lines configured yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
