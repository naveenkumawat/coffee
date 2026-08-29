@extends('barista.layouts.default')

@section('page-title', 'Products')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Products'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('barista.products.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-4 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Product, SKU, flavour" />
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
                <div class="col-xl-3 col-md-3">
                    <label for="product_flavour_id" class="form-label">Flavour</label>
                    <select id="product_flavour_id" name="product_flavour_id" class="form-select">
                        <option value="">All flavours</option>
                        @foreach ($flavourOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('product_flavour_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-12">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('barista.products.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>Default Variant</th>
                            <th>Flavours</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($products as $product)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $product->name }}</span>
                                        <span class="text-muted">{{ $product->short_description ?: 'No summary provided.' }}</span>
                                    </div>
                                </td>
                                <td>{{ $product->category?->name }}</td>
                                <td>
                                    {{ $product->defaultVariant?->name ?: 'No active variant' }}
                                    @if ($product->defaultVariant)
                                        <div class="text-gray-500 fs-7">${{ number_format((float) $product->defaultVariant->price, 2) }}</div>
                                    @endif
                                </td>
                                <td>{{ $product->flavours->pluck('name')->join(', ') ?: 'None' }}</td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('barista.products.show', $product), 'icon' => 'ki-eye'],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No products matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $products->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
