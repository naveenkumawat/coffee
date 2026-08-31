@extends('administrator.layouts.default')

@section('page-title', 'Café Tables')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Café Tables'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Table', 'url' => route('administrator.cafe-tables.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="alert alert-primary mb-8">
        Active tables appear in the customer PWA checkout when dine-in is enabled in Website Settings.
        Renaming or archiving a table does not change historical order snapshots.
    </div>

    <div class="card card-flush internal-card">
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Code</th>
                            <th>Name</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($tables as $table)
                            <tr>
                                <td>
                                    <span class="text-gray-900 fw-bold">{{ $table->code }}</span>
                                </td>
                                <td>{{ $table->name ?: '—' }}</td>
                                <td>{{ $table->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $table->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $table->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'Edit', 'url' => route('administrator.cafe-tables.edit', $table), 'icon' => 'ki-notepad-edit'],
                                        [
                                            'label' => 'Move up',
                                            'url' => route('administrator.cafe-tables.move-up', $table),
                                            'method' => 'PATCH',
                                            'icon' => 'ki-arrow-up',
                                        ],
                                        [
                                            'label' => 'Move down',
                                            'url' => route('administrator.cafe-tables.move-down', $table),
                                            'method' => 'PATCH',
                                            'icon' => 'ki-arrow-down',
                                        ],
                                        [
                                            'label' => $table->is_active ? 'Deactivate' : 'Activate',
                                            'url' => route('administrator.cafe-tables.toggle', $table),
                                            'method' => 'PATCH',
                                            'icon' => $table->is_active ? 'ki-cross-circle' : 'ki-check-circle',
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.cafe-tables.destroy', $table),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this café table? Historical orders keep the table snapshot.',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-10">No café tables yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $tables->links('components.internal.pagination') }}
            </div>
        </div>
    </div>
@endsection
