@extends('administrator.layouts.default')

@section('page-title', 'Menu Items')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Menu Items'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Item', 'url' => route('administrator.menu.items.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Item</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Flags</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $item->name }}</span>
                                        <span class="text-muted">{{ $item->description ?: 'No description provided.' }}</span>
                                    </div>
                                </td>
                                <td>{{ $item->category?->name }}</td>
                                <td>${{ number_format((float) $item->price, 2) }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge {{ $item->is_available ? 'badge-light-success' : 'badge-light-danger' }}">
                                            {{ $item->is_available ? 'Available' : 'Paused' }}
                                        </span>
                                        @if ($item->is_featured)
                                            <span class="badge badge-light-warning">Featured</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        [
                                            'label' => 'Edit',
                                            'url' => route('administrator.menu.items.edit', $item),
                                            'icon' => 'ki-notepad-edit',
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => $item->is_available ? 'Available' : 'Paused',
                                            'icon' => $item->is_available ? 'ki-check-circle' : 'ki-cross-circle',
                                            'disabled' => true,
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Delete',
                                            'url' => route('administrator.menu.items.destroy', $item),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Delete this menu item?',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No menu items created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $items->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
