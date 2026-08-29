@extends('administrator.layouts.default')

@section('page-title', $flavour->name)

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Product Flavours', 'url' => route('administrator.products.flavours.index')],
        ['label' => $flavour->name],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.products.flavours.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'Edit', 'url' => route('administrator.products.flavours.edit', $flavour), 'variant' => 'success', 'icon' => 'ki-notepad-edit'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10">
        <div class="col-xl-4">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Flavour Overview</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="d-flex flex-column gap-5">
                        <div>
                            <div class="text-muted fs-7 mb-1">Name</div>
                            <div class="fw-bold text-gray-900">{{ $flavour->name }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Slug</div>
                            <div class="text-gray-700">{{ $flavour->slug }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Status</div>
                            <span class="badge {{ $flavour->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                {{ $flavour->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Applicable Categories</div>
                            <div class="text-gray-700">{{ $flavour->categories->pluck('name')->join(', ') ?: 'All / not limited' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Description</div>
                            <div class="text-gray-700">{{ $flavour->description ?: 'No description provided.' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Products Using This Flavour</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive internal-table-wrapper">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Default Variant</th>
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
                                                <span class="text-muted">{{ $product->short_description ?: 'No summary provided.' }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $product->category?->name }}</td>
                                        <td>{{ $product->defaultVariant?->name ?: 'No active variant' }}</td>
                                        <td>
                                            <span class="badge {{ $product->is_available ? 'badge-light-success' : 'badge-light-danger' }}">
                                                {{ $product->is_available ? 'Available' : 'Unavailable' }}
                                            </span>
                                        </td>
                                        <td class="text-end internal-action-cell">
                                            <x-internal.action-dropdown :items="[
                                                ['label' => 'View', 'url' => route('administrator.products.show', $product), 'icon' => 'ki-eye'],
                                                ['label' => 'Edit', 'url' => route('administrator.products.edit', $product), 'icon' => 'ki-notepad-edit'],
                                            ]" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-10">No products use this flavour yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $products->links('components.internal.pagination') }}
                </div>
            </div>
        </div>
    </div>
@endsection
