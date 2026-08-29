@extends('administrator.layouts.default')

@section('page-title', 'Ingredient Categories')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Ingredient Categories'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'New Category',
            'url' => route('administrator.ingredients.categories.create'),
            'variant' => 'success',
            'icon' => 'ki-plus',
        ],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Category</th>
                            <th>Slug</th>
                            <th>Ingredients</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $category->name }}</span>
                                        <span class="text-muted">{{ $category->description ?: 'No description provided.' }}</span>
                                    </div>
                                </td>
                                <td>{{ $category->slug }}</td>
                                <td>{{ $category->ingredients_count }}</td>
                                <td>
                                    <span class="badge {{ $category->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        [
                                            'label' => 'View',
                                            'url' => route('administrator.ingredients.categories.show', $category),
                                            'icon' => 'ki-eye',
                                        ],
                                        [
                                            'label' => 'Edit',
                                            'url' => route('administrator.ingredients.categories.edit', $category),
                                            'icon' => 'ki-notepad-edit',
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => $category->is_active ? 'Active' : 'Inactive',
                                            'icon' => $category->is_active ? 'ki-check-circle' : 'ki-cross-circle',
                                            'disabled' => true,
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.ingredients.categories.destroy', $category),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this ingredient category?',
                                            'disabled' => $category->ingredients_count > 0,
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No ingredient categories available yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $categories->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
