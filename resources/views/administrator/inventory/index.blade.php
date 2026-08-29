@extends('administrator.layouts.default')

@section('page-title', 'Inventory Overview')

@section('page-description', $pendingRefillCount.' refill request(s) currently pending review.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Inventory'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'History',
            'url' => route('administrator.inventory.history'),
            'variant' => 'dark',
            'icon' => 'ki-time',
        ],
        [
            'label' => 'Record Movement',
            'url' => route('administrator.inventory.movements.create'),
            'variant' => 'success',
            'icon' => 'ki-plus',
        ],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.inventory.index') }}" class="row g-6 align-items-end internal-filter-form">
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
                        ['label' => 'Reset', 'url' => route('administrator.inventory.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
                    ]" justify="start" />
                </div>
            </form>
        </div>
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Ingredient</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Thresholds</th>
                            <th>Latest Movement</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($inventoryItems as $ingredient)
                            @php
                                $status = $ingredient->stockStatus();
                                $latestTransaction = $ingredient->latestInventoryTransaction;
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $ingredient->name }}</span>
                                        <span class="text-muted">{{ $ingredient->brand?->name ?: 'No brand assigned' }}</span>
                                        <span class="text-gray-500 fs-7">{{ $ingredient->measurement_unit->value }} purchase / {{ $ingredient->base_measurement_unit->value }} stock</span>
                                    </div>
                                </td>
                                <td>{{ $ingredient->category?->name }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->base_measurement_unit->value }}</span>
                                        <span class="text-gray-500 fs-7">Purchase cost Rs {{ number_format((float) $ingredient->purchase_cost, 2) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span>Minimum {{ number_format((float) $ingredient->minimum_stock, 3) }}</span>
                                        <span class="text-gray-500 fs-7">Reorder {{ number_format((float) $ingredient->reorder_level, 3) }} {{ $ingredient->base_measurement_unit->value }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if ($latestTransaction)
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-900">{{ $latestTransaction->transaction_type->label() }}</span>
                                            <span class="text-gray-500 fs-7">{{ $latestTransaction->created_at?->diffForHumans() }}</span>
                                            <span class="text-gray-500 fs-7">{{ $latestTransaction->createdBy?->name ?: 'System' }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted">No transactions yet</span>
                                    @endif
                                </td>
                                <td>
                                    <x-internal.stock-badge :status="$status" />
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        [
                                            'label' => 'View Ingredient',
                                            'url' => route('administrator.ingredients.show', $ingredient),
                                            'icon' => 'ki-eye',
                                        ],
                                        [
                                            'label' => 'History',
                                            'url' => route('administrator.inventory.history', ['ingredient_id' => $ingredient->id]),
                                            'icon' => 'ki-time',
                                        ],
                                        [
                                            'label' => 'Refill Requests',
                                            'url' => route('administrator.inventory.refill-requests.index', ['ingredient_id' => $ingredient->id]),
                                            'icon' => 'ki-delivery-3',
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Add Stock',
                                            'url' => route('administrator.inventory.movements.create', ['ingredient_id' => $ingredient->id, 'transaction_type' => 'stock_added']),
                                            'icon' => 'ki-plus-circle',
                                        ],
                                        [
                                            'label' => 'Adjustment',
                                            'url' => route('administrator.inventory.movements.create', ['ingredient_id' => $ingredient->id, 'transaction_type' => 'manual_adjustment']),
                                            'icon' => 'ki-arrows-circle',
                                        ],
                                        [
                                            'label' => 'Wastage',
                                            'url' => route('administrator.inventory.movements.create', ['ingredient_id' => $ingredient->id, 'transaction_type' => 'wastage']),
                                            'icon' => 'ki-trash-square',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-10">No inventory items matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $inventoryItems->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
