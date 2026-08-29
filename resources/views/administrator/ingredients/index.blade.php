@extends('administrator.layouts.default')

@section('page-title', 'Ingredients')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredients'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'New Ingredient',
            'url' => route('administrator.ingredients.create'),
            'variant' => 'success',
            'icon' => 'ki-plus',
        ],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.ingredients.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-4 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Ingredient, brand, description, supplier" />
                </div>
                <div class="col-xl-3 col-md-3">
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
                <div class="col-xl-1 col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.ingredients.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>Purchase</th>
                            <th>Unit Cost</th>
                            <th>Stock</th>
                            <th>Availability</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($ingredients as $ingredient)
                            <tr>
                        <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $ingredient->name }}</span>
                                        <span class="text-muted">{{ $ingredient->brand?->name ?: 'No brand assigned' }}</span>
                                        <span class="text-gray-500 fs-7">{{ $ingredient->supplier_name ?: 'No supplier assigned' }}</span>
                                    </div>
                                </td>
                                <td>{{ $ingredient->category?->name }}</td>
                                <td>{{ number_format((float) $ingredient->purchase_quantity, 3) }} {{ $ingredient->measurement_unit->value }} / Rs {{ number_format((float) $ingredient->purchase_cost, 2) }}</td>
                                <td>Rs {{ number_format((float) $ingredient->cost_per_unit, 4) }}/{{ $ingredient->base_measurement_unit->value }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span>{{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->base_measurement_unit->value }}</span>
                                        <span class="text-gray-500 fs-7">Min {{ number_format((float) $ingredient->minimum_stock, 3) }} | Reorder {{ number_format((float) $ingredient->reorder_level, 3) }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-2">
                                        <x-internal.stock-badge :status="$ingredient->stockStatus()" />
                                        <span class="badge {{ $ingredient->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                            {{ $ingredient->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        [
                                            'label' => 'View',
                                            'url' => route('administrator.ingredients.show', $ingredient),
                                            'icon' => 'ki-eye',
                                        ],
                                        [
                                            'label' => 'Edit',
                                            'url' => route('administrator.ingredients.edit', $ingredient),
                                            'icon' => 'ki-notepad-edit',
                                        ],
                                        [
                                            'label' => 'Inventory History',
                                            'url' => route('administrator.inventory.history', ['ingredient_id' => $ingredient->id]),
                                            'icon' => 'ki-time',
                                        ],
                                        [
                                            'label' => 'Record Movement',
                                            'url' => route('administrator.inventory.movements.create', ['ingredient_id' => $ingredient->id]),
                                            'icon' => 'ki-plus-circle',
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => $ingredient->is_active ? 'Active' : 'Inactive',
                                            'icon' => $ingredient->is_active ? 'ki-check-circle' : 'ki-cross-circle',
                                            'disabled' => true,
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.ingredients.destroy', $ingredient),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this ingredient?',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-10">No ingredients matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $ingredients->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
