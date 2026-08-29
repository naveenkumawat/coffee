@extends('administrator.layouts.default')

@section('page-title', 'Ingredient Brands')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredient Brands'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Brand', 'url' => route('administrator.ingredients.brands.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.ingredients.brands.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-lg-8 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Brand name, slug, description" />
                </div>
                <div class="col-lg-2 col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.ingredients.brands.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>Brand</th>
                            <th>Slug</th>
                            <th>Ingredients</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($brands as $brand)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $brand->name }}</span>
                                        <span class="text-muted">{{ $brand->description ?: 'No description provided.' }}</span>
                                    </div>
                                </td>
                                <td>{{ $brand->slug }}</td>
                                <td>{{ $brand->ingredients_count }}</td>
                                <td>
                                    <span class="badge {{ $brand->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $brand->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('administrator.ingredients.brands.show', $brand), 'icon' => 'ki-eye'],
                                        ['label' => 'Edit', 'url' => route('administrator.ingredients.brands.edit', $brand), 'icon' => 'ki-notepad-edit'],
                                        ['type' => 'separator'],
                                        ['label' => $brand->is_active ? 'Active' : 'Inactive', 'icon' => $brand->is_active ? 'ki-check-circle' : 'ki-cross-circle', 'disabled' => true],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.ingredients.brands.destroy', $brand),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this ingredient brand?',
                                            'disabled' => $brand->ingredients_count > 0,
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No ingredient brands available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $brands->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
