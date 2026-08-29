@extends('administrator.layouts.default')

@section('page-title', 'Product Flavours')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Product Flavours'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Flavour', 'url' => route('administrator.products.flavours.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.products.flavours.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-6 col-md-5">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Flavour name, slug, description" />
                </div>
                <div class="col-xl-3 col-md-3">
                    <label for="product_category_id" class="form-label">Category</label>
                    <select id="product_category_id" name="product_category_id" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('product_category_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-1 col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-2">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.products.flavours.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>Flavour</th>
                            <th>Categories</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($flavours as $flavour)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $flavour->name }}</span>
                                        <span class="text-muted">{{ $flavour->description ?: 'No description provided.' }}</span>
                                    </div>
                                </td>
                                <td>{{ $flavour->categories_count }}</td>
                                <td>{{ $flavour->products_count }}</td>
                                <td>
                                    <span class="badge {{ $flavour->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $flavour->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('administrator.products.flavours.show', $flavour), 'icon' => 'ki-eye'],
                                        ['label' => 'Edit', 'url' => route('administrator.products.flavours.edit', $flavour), 'icon' => 'ki-notepad-edit'],
                                        ['type' => 'separator'],
                                        ['label' => $flavour->is_active ? 'Active' : 'Inactive', 'icon' => $flavour->is_active ? 'ki-check-circle' : 'ki-cross-circle', 'disabled' => true],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.products.flavours.destroy', $flavour),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this product flavour?',
                                            'disabled' => $flavour->products_count > 0,
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No product flavours available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $flavours->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
