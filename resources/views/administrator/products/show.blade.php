@extends('administrator.layouts.default')

@section('page-title', $product->name)

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Products', 'url' => route('administrator.products.index')],
        ['label' => $product->name],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.products.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'Edit', 'url' => route('administrator.products.edit', $product), 'variant' => 'success', 'icon' => 'ki-notepad-edit'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-5">
        <div class="col-xl-5">
            <div class="card card-flush internal-card h-xl-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Product Overview</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="d-flex flex-column gap-5">
                        <div>
                            <div class="text-muted fs-7 mb-1">Category</div>
                            <div class="fw-bold text-gray-900">{{ $product->category?->name }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">SKU</div>
                            <div class="text-gray-700">{{ $product->sku ?: 'No SKU assigned' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Slug</div>
                            <div class="text-gray-700">{{ $product->slug }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Customer Summary</div>
                            <div class="text-gray-700">{{ $product->customer_ingredient_summary ?: 'Not provided.' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Description</div>
                            <div class="text-gray-700">{{ $product->description ?: 'No detailed description provided.' }}</div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Launch readiness</div>
                            <div class="d-flex flex-column gap-2">
                                <span class="badge {{ $readiness->isReady() ? 'badge-light-success' : 'badge-light-danger' }}">
                                    {{ $readiness->statusLabel() }}
                                </span>
                                @if ($readiness->availabilityLabel((bool) $product->is_available))
                                    <span class="badge badge-light-warning">{{ $readiness->availabilityLabel((bool) $product->is_available) }}</span>
                                @endif
                                @if (! $readiness->isReady())
                                    <div class="text-gray-700 fs-7">
                                        <div class="fw-semibold mb-1">Missing:</div>
                                        <ul class="mb-0 ps-4">
                                            @foreach ($readiness->missing as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @elseif ($readiness->hasInventoryConcern())
                                    <div class="text-gray-700 fs-7">
                                        <div class="fw-semibold mb-1">Inventory notes:</div>
                                        <ul class="mb-0 ps-4">
                                            @foreach ($readiness->inventoryNotes as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Flags</div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge {{ $product->is_active ? 'badge-light-success' : 'badge-light-warning' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span>
                                <span class="badge {{ $product->is_available ? 'badge-light-primary' : 'badge-light-danger' }}">{{ $product->is_available ? 'Available' : 'Unavailable' }}</span>
                                @if ($product->is_vegetarian)
                                    <span class="badge badge-light-success">Vegetarian</span>
                                @endif
                                @if ($product->is_customizable)
                                    <span class="badge badge-light-primary">Customizable</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Marketing tags</div>
                            <div class="d-flex flex-wrap gap-2">
                                @forelse ($product->tags as $tag)
                                    <span class="badge badge-light-info">{{ $tag->name }}</span>
                                @empty
                                    <span class="text-gray-700">No marketing tags assigned.</span>
                                @endforelse
                            </div>
                        </div>
                        <div>
                            <div class="text-muted fs-7 mb-1">Flavours</div>
                            <div class="text-gray-700">{{ $product->flavours->pluck('name')->join(', ') ?: 'No optional flavours linked.' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card card-flush internal-card h-xl-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Sellable Variants</h3>
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
                                            <div class="d-flex flex-wrap gap-2">
                                                <span class="badge {{ $variant->is_active ? 'badge-light-success' : 'badge-light-warning' }}">{{ $variant->is_active ? 'Active' : 'Inactive' }}</span>
                                                <span class="badge {{ $variant->is_available ? 'badge-light-primary' : 'badge-light-danger' }}">{{ $variant->is_available ? 'Available' : 'Unavailable' }}</span>
                                            </div>
                                        </td>
                                        <td class="text-end internal-action-cell">
                                            <x-internal.action-dropdown :items="[
                                                ['label' => 'View Recipe', 'url' => $variant->recipe ? route('administrator.recipes.show', $variant->recipe) : '#', 'icon' => 'ki-eye', 'visible' => (bool) $variant->recipe],
                                                ['label' => 'Edit Recipe', 'url' => $variant->recipe ? route('administrator.recipes.edit', $variant->recipe) : '#', 'icon' => 'ki-notepad-edit', 'visible' => (bool) $variant->recipe],
                                                ['label' => 'Create Recipe', 'url' => route('administrator.recipes.create', ['product_variant_id' => $variant->id]), 'icon' => 'ki-plus', 'visible' => ! $variant->recipe],
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
