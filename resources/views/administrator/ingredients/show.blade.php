@extends('administrator.layouts.default')

@section('page-title', 'Ingredient Details')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredients', 'url' => route('administrator.ingredients.index')],
        ['label' => 'Details'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'Back',
            'url' => route('administrator.ingredients.index'),
            'variant' => 'dark',
            'icon' => 'ki-left',
        ],
        [
            'label' => 'Edit Ingredient',
            'url' => route('administrator.ingredients.edit', $ingredient),
            'variant' => 'success',
            'icon' => 'ki-notepad-edit',
        ],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10">
        <div class="col-xl-8">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Ingredient Profile</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="row g-6">
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Name</div>
                            <div class="fw-bold text-gray-900">{{ $ingredient->name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Category</div>
                            <div class="fw-bold text-gray-900">{{ $ingredient->category?->name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Brand</div>
                            <div class="fw-bold text-gray-900">{{ $ingredient->brand?->name ?: 'Not assigned' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Status</div>
                            <span class="badge {{ $ingredient->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                {{ $ingredient->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Purchase Quantity</div>
                            <div class="fw-bold text-gray-900">{{ number_format((float) $ingredient->purchase_quantity, 3) }} {{ $ingredient->measurement_unit->value }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Purchase Cost</div>
                            <div class="fw-bold text-gray-900">Rs {{ number_format((float) $ingredient->purchase_cost, 2) }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Calculated Unit Cost</div>
                            <div class="fw-bold text-gray-900">Rs {{ number_format((float) $ingredient->cost_per_unit, 4) }}/{{ $ingredient->base_measurement_unit->value }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Base Quantity</div>
                            <div class="fw-bold text-gray-900">{{ number_format((float) $ingredient->purchase_quantity_base, 3) }} {{ $ingredient->base_measurement_unit->value }}</div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted fs-7 mb-1">Description</div>
                            <div class="text-gray-700">{{ $ingredient->description ?: 'No description provided.' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush internal-card mb-5">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Stock Thresholds</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Current Stock</div>
                        <div class="fw-bold text-gray-900">{{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->base_measurement_unit->value }}</div>
                    </div>
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Minimum Stock</div>
                        <div class="fw-bold text-gray-900">{{ number_format((float) $ingredient->minimum_stock, 3) }} {{ $ingredient->base_measurement_unit->value }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-7 mb-1">Reorder Level</div>
                        <div class="fw-bold text-gray-900">{{ number_format((float) $ingredient->reorder_level, 3) }} {{ $ingredient->base_measurement_unit->value }}</div>
                    </div>
                </div>
            </div>

            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Supplier Metadata</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Supplier Name</div>
                        <div class="fw-bold text-gray-900">{{ $ingredient->supplier_name ?: 'Not provided' }}</div>
                    </div>
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Supplier Email</div>
                        <div class="text-gray-700">{{ $ingredient->supplier_email ?: 'Not provided' }}</div>
                    </div>
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Supplier Phone</div>
                        <div class="text-gray-700">{{ $ingredient->supplier_phone ?: 'Not provided' }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-7 mb-1">Notes</div>
                        <div class="text-gray-700">{{ $ingredient->supplier_notes ?: 'No supplier notes provided.' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
