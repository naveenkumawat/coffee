@extends('administrator.layouts.default')

@section('page-title', 'Product Tags')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Product Tags'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Tag', 'url' => route('administrator.products.tags.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Tag</th>
                            <th>Style</th>
                            <th>Order</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($tags as $tag)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $tag->name }}</span>
                                        <span class="text-muted">{{ $tag->slug }}</span>
                                    </div>
                                </td>
                                <td>{{ $tag->style_key?->value ?? $tag->style_key }}</td>
                                <td>{{ $tag->sort_order }}</td>
                                <td>{{ $tag->products_count }}</td>
                                <td>
                                    <span class="badge {{ $tag->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $tag->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'Edit', 'url' => route('administrator.products.tags.edit', $tag), 'icon' => 'ki-notepad-edit'],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.products.tags.destroy', $tag),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this product tag?',
                                            'disabled' => $tag->products_count > 0,
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">No product tags yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $tags->links('components.internal.pagination') }}
            </div>
        </div>
    </div>
@endsection
