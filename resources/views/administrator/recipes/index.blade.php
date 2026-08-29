@extends('administrator.layouts.default')

@section('page-title', 'Recipes')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Recipes'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Recipe', 'url' => route('administrator.recipes.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.recipes.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-4 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Product, variant, notes, ingredient" />
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
                    <label for="product_id" class="form-label">Product</label>
                    <select id="product_id" name="product_id" class="form-select">
                        <option value="">All products</option>
                        @foreach ($productOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('product_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="ingredient_id" class="form-label">Ingredient</label>
                    <select id="ingredient_id" name="ingredient_id" class="form-select">
                        <option value="">All ingredients</option>
                        @foreach ($ingredientOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('ingredient_id') === (string) $id)>{{ $name }}</option>
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
                <div class="col-xl-1 col-md-4">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.recipes.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>Variant</th>
                            <th>Ingredients</th>
                            <th>Production Cost</th>
                            <th>Profitability</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($recipes as $recipe)
                            @php($summary = $costingByRecipe[$recipe->id] ?? null)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $recipe->variant?->product?->name }}</span>
                                        <span class="text-muted">{{ $recipe->variant?->name }}{{ $recipe->variant?->product?->category?->name ? ' • '.$recipe->variant->product->category->name : '' }}</span>
                                    </div>
                                </td>
                                <td>{{ $recipe->lines_count }}</td>
                                <td>Rs {{ number_format((float) ($summary['production_cost'] ?? 0), 4) }}</td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span>Rs {{ number_format((float) ($summary['gross_profit'] ?? 0), 4) }}</span>
                                        <span class="text-gray-500 fs-7">{{ $summary['margin_percentage'] ?? '0.00' }}% margin</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $recipe->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $recipe->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('administrator.recipes.show', $recipe), 'icon' => 'ki-eye'],
                                        ['label' => 'Edit', 'url' => route('administrator.recipes.edit', $recipe), 'icon' => 'ki-notepad-edit'],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.recipes.destroy', $recipe),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this recipe?',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">No recipes matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $recipes->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
