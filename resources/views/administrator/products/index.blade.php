@extends('administrator.layouts.default')

@section('page-title', 'Products')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Products'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Product', 'url' => route('administrator.products.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.products.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-4 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Product, SKU, description, flavour" />
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="product_category_id" class="form-label">Category</label>
                    <select id="product_category_id" name="product_category_id" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($categoryOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('product_category_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="product_flavour_id" class="form-label">Flavour</label>
                    <select id="product_flavour_id" name="product_flavour_id" class="form-select">
                        <option value="">All flavours</option>
                        @foreach ($flavourOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('product_flavour_id') === (string) $id)>{{ $name }}</option>
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
                <div class="col-xl-1 col-md-2">
                    <label for="availability" class="form-label">Availability</label>
                    <select id="availability" name="availability" class="form-select">
                        <option value="">All</option>
                        <option value="available" @selected(request('availability') === 'available')>Live</option>
                        <option value="unavailable" @selected(request('availability') === 'unavailable')>Paused</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.products.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>Product</th>
                            <th>Category</th>
                            <th>Variant Pricing</th>
                            <th>Flavours</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $product->name }}</span>
                                        <span class="text-muted">{{ $product->sku ?: 'No SKU assigned' }}</span>
                                        <span class="text-gray-500 fs-7">{{ $product->short_description ?: 'No summary provided.' }}</span>
                                    </div>
                                </td>
                                <td>{{ $product->category?->name }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span>{{ $product->defaultVariant?->name ?: 'No active variant' }}</span>
                                        <span class="text-gray-500 fs-7">${{ number_format((float) ($product->defaultVariant?->price ?? 0), 2) }} default</span>
                                    </div>
                                </td>
                                <td>{{ $product->flavours->pluck('name')->join(', ') ?: 'None' }}</td>
                                <td>
                                    <div class="d-flex flex-column gap-2">
                                        <span class="badge {{ $product->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                            {{ $product->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                        <span class="badge {{ $product->is_available ? 'badge-light-primary' : 'badge-light-danger' }}">
                                            {{ $product->is_available ? 'Available' : 'Unavailable' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('administrator.products.show', $product), 'icon' => 'ki-eye'],
                                        ['label' => 'Edit', 'url' => route('administrator.products.edit', $product), 'icon' => 'ki-notepad-edit'],
                                        ['type' => 'separator'],
                                        ['label' => $product->is_featured ? 'Featured' : 'Standard', 'icon' => $product->is_featured ? 'ki-star' : 'ki-badge', 'disabled' => true],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.products.destroy', $product),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this product and its variants?',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">No products matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $products->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
