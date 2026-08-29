@extends('barista.layouts.default')

@section('page-title', $product->name)

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Products', 'url' => route('barista.products.index')],
        ['label' => $product->name],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('barista.products.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10">
        <div class="col-xl-5">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Product Snapshot</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="d-flex flex-column gap-5">
                        <div>
                            <div class="text-muted fs-7 mb-1">Category</div>
                            <div class="fw-bold text-gray-900">{{ $product->category?->name }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Customer Summary</div>
                            <div class="text-gray-700">{{ $product->customer_ingredient_summary ?: 'Not provided.' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Flavours</div>
                            <div class="text-gray-700">{{ $product->flavours->pluck('name')->join(', ') ?: 'No optional flavours linked.' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Description</div>
                            <div class="text-gray-700">{{ $product->description ?: 'No detailed description provided.' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Available Variants</h3>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive internal-table-wrapper">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th>Variant</th>
                                    <th>Serving Size</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th class="text-end internal-action-header">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="fw-semibold text-gray-600">
                                @forelse ($product->variants as $variant)
                                    <tr>
                                        <td>{{ $variant->name }}</td>
                                        <td>{{ number_format((float) $variant->serving_size_value, 3) }} {{ $variant->serving_size_unit->value }}</td>
                                        <td>${{ number_format((float) $variant->price, 2) }}</td>
                                        <td>
                                            <span class="badge {{ $variant->is_available ? 'badge-light-primary' : 'badge-light-danger' }}">
                                                {{ $variant->is_available ? 'Available' : 'Unavailable' }}
                                            </span>
                                        </td>
                                        <td class="text-end internal-action-cell">
                                            <x-internal.action-dropdown :items="[
                                                [
                                                    'label' => $variant->recipe ? 'View Recipe' : 'Recipe Not Ready',
                                                    'url' => $variant->recipe ? route('barista.recipes.show', $variant->recipe) : '#',
                                                    'icon' => $variant->recipe ? 'ki-eye' : 'ki-cross-circle',
                                                    'disabled' => ! $variant->recipe,
                                                ],
                                            ]" />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-10">No variants configured yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
