@extends('administrator.layouts.default')

@section('page-title', 'Ingredient Category Details')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredient Categories', 'url' => route('administrator.ingredients.categories.index')],
        ['label' => 'Details'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'New Ingredient',
            'url' => route('administrator.ingredients.create', ['ingredient_category_id' => $category->id]),
            'variant' => 'success',
            'icon' => 'ki-plus',
        ],
        [
            'label' => 'Edit Category',
            'url' => route('administrator.ingredients.categories.edit', $category),
            'variant' => 'dark',
            'icon' => 'ki-notepad-edit',
        ],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10">
        <div class="col-xl-4">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Category Profile</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-6">
                        <div class="text-muted fs-7 mb-1">Name</div>
                        <div class="fw-bold text-gray-900">{{ $category->name }}</div>
                    </div>
                    <div class="mb-6">
                        <div class="text-muted fs-7 mb-1">Slug</div>
                        <div class="fw-bold text-gray-900">{{ $category->slug }}</div>
                    </div>
                    <div class="mb-6">
                        <div class="text-muted fs-7 mb-1">Status</div>
                        <span class="badge {{ $category->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                            {{ $category->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div>
                        <div class="text-muted fs-7 mb-1">Description</div>
                        <div class="text-gray-700">{{ $category->description ?: 'No description provided.' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Ingredients in This Category</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive internal-table-wrapper">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th>Ingredient</th>
                                    <th>Unit Cost</th>
                                    <th>Stock</th>
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
                                                <span class="text-muted">{{ $ingredient->brand?->name ?: 'No brand assigned' }}</span>
                                            </div>
                                        </td>
                                        <td>Rs {{ number_format((float) $ingredient->cost_per_unit, 4) }}/{{ $ingredient->base_measurement_unit->value }}</td>
                                        <td>{{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->base_measurement_unit->value }}</td>
                                        <td>
                                            <span class="badge {{ $ingredient->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                                {{ $ingredient->is_active ? 'Active' : 'Inactive' }}
                                            </span>
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
                                            ]" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-10">No ingredients belong to this category yet.</td>
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
