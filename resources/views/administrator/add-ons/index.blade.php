@extends('administrator.layouts.default')

@section('page-title', 'Product Add-ons')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Add-ons'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Add-on', 'url' => route('administrator.add-ons.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card">
        <div class="card-header border-0 pt-6">
            <form method="GET" class="d-flex gap-3 w-100 w-md-50">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search add-ons" />
                <button class="btn btn-light" type="submit">Search</button>
            </form>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive internal-table-wrapper">
                <table class="table align-middle table-row-dashed fs-6 gy-5 internal-table">
                    <thead>
                        <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                            <th>Name</th>
                            <th>Default price</th>
                            <th>Recipe lines</th>
                            <th>Order</th>
                            <th>Status</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($addOns as $addOn)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $addOn->name }}</span>
                                        <span class="text-muted">{{ $addOn->slug }}</span>
                                    </div>
                                </td>
                                <td>{{ number_format((float) $addOn->default_price, 2) }}</td>
                                <td>{{ $addOn->recipe_lines_count ?? $addOn->recipeLines()->count() }}</td>
                                <td>{{ $addOn->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $addOn->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $addOn->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'Edit', 'url' => route('administrator.add-ons.edit', $addOn), 'icon' => 'ki-notepad-edit'],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.add-ons.destroy', $addOn),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this add-on?',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">No add-ons yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-6">
                {{ $addOns->links('components.internal.pagination') }}
            </div>
        </div>
    </div>
@endsection
