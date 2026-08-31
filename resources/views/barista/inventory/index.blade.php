@extends('barista.layouts.default')

@section('page-title', 'Inventory')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Inventory'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('barista.inventory.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-4 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Ingredient, brand, category, or supplier" />
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="ingredient_category_id" class="form-label">Category</label>
                    <select id="ingredient_category_id" name="ingredient_category_id" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('ingredient_category_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="ingredient_brand_id" class="form-label">Brand</label>
                    <select id="ingredient_brand_id" name="ingredient_brand_id" class="form-select">
                        <option value="">All brands</option>
                        @foreach ($brandOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('ingredient_brand_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="measurement_unit" class="form-label">Unit</label>
                    <select id="measurement_unit" name="measurement_unit" class="form-select">
                        <option value="">All units</option>
                        @foreach ($unitOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('measurement_unit') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="stock_status" class="form-label">Stock Status</label>
                    <select id="stock_status" name="stock_status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($stockStatusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('stock_status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-12">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('barista.inventory.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
                    ]" justify="start" />
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-header pt-7">
            <div class="card-title">
                <h3 class="fw-bold text-gray-900">Inventory</h3>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Ingredient</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Thresholds</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($inventoryItems as $ingredient)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $ingredient->name }}</span>
                                        <span class="text-muted">{{ $ingredient->brand?->name ?: 'No brand assigned' }}</span>
                                    </div>
                                </td>
                                <td>{{ $ingredient->category?->name }}</td>
                                <td>{{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->base_measurement_unit->value }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span>Minimum {{ number_format((float) $ingredient->minimum_stock, 3) }}</span>
                                        <span class="text-gray-500 fs-7">Reorder {{ number_format((float) $ingredient->reorder_level, 3) }} {{ $ingredient->base_measurement_unit->value }}</span>
                                    </div>
                                </td>
                                <td>
                                    <x-internal.stock-badge :status="$ingredient->stockStatus()" />
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        [
                                            'label' => 'Request Refill',
                                            'url' => route('barista.refill-requests.create', ['ingredient_id' => $ingredient->id]),
                                            'icon' => 'ki-plus-circle',
                                        ],
                                        [
                                            'label' => 'My Requests',
                                            'url' => route('barista.refill-requests.index', ['ingredient_id' => $ingredient->id]),
                                            'icon' => 'ki-time',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">No inventory items matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $inventoryItems->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
