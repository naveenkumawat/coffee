@extends('administrator.layouts.default')

@section('page-title', 'Ingredient Brand Details')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredient Brands', 'url' => route('administrator.ingredients.brands.index')],
        ['label' => 'Details'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.ingredients.brands.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'Edit Brand', 'url' => route('administrator.ingredients.brands.edit', $brand), 'variant' => 'success', 'icon' => 'ki-notepad-edit'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10">
        <div class="col-xl-4">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Brand Profile</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-6">
                        <div class="text-muted fs-7 mb-1">Name</div>
                        <div class="fw-bold text-gray-900">{{ $brand->name }}</div>
                    </div>
                    <div class="mb-6">
                        <div class="text-muted fs-7 mb-1">Slug</div>
                        <div class="fw-bold text-gray-900">{{ $brand->slug }}</div>
                    </div>
                    <div class="mb-6">
                        <div class="text-muted fs-7 mb-1">Status</div>
                        <span class="badge {{ $brand->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                            {{ $brand->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div>
                        <div class="text-muted fs-7 mb-1">Description</div>
                        <div class="text-gray-700">{{ $brand->description ?: 'No description provided.' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Ingredients Using This Brand</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive internal-table-wrapper">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th>Ingredient</th>
                                    <th>Category</th>
                                    <th>Unit Cost</th>
                                    <th>Status</th>
                                    <th class="text-end internal-action-header">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-600">
                                @forelse ($ingredients as $ingredient)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-900 fw-bold">{{ $ingredient->name }}</span>
                                                <span class="text-muted">{{ $ingredient->supplier_name ?: 'No supplier assigned' }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $ingredient->category?->name ?: 'Uncategorized' }}</td>
                                        <td>Rs {{ number_format((float) $ingredient->cost_per_unit, 4) }}/{{ $ingredient->base_measurement_unit->value }}</td>
                                        <td>
                                            <span class="badge {{ $ingredient->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                                {{ $ingredient->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="text-end internal-action-cell">
                                            <x-internal.action-dropdown :items="[
                                                ['label' => 'View', 'url' => route('administrator.ingredients.show', $ingredient), 'icon' => 'ki-eye'],
                                                ['label' => 'Edit', 'url' => route('administrator.ingredients.edit', $ingredient), 'icon' => 'ki-notepad-edit'],
                                            ]" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-10">No ingredients use this brand yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $ingredients->links('components.internal.pagination') }}
                </div>
            </div>
        </div>
    </div>
@endsection
