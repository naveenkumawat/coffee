@extends('administrator.layouts.default')

@section('page-title', 'Homepage Sections')

@section('page-description', 'Administrator-managed merchandising rails with manual product assignment and ordering.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Homepage Sections'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'New Section', 'url' => route('administrator.home-sections.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.home-sections.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-lg-9 col-md-8">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Title, name, slug, subtitle" />
                </div>
                <div class="col-lg-1 col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-2">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.home-sections.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>Title</th>
                            <th>Products</th>
                            <th>Status</th>
                            <th>Sort</th>
                            <th>Updated</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($sections as $section)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $section->title }}</span>
                                        <span class="text-muted">{{ $section->subtitle ?: $section->slug }}</span>
                                    </div>
                                </td>
                                <td>{{ $section->section_products_count }}</td>
                                <td>
                                    <span class="badge {{ $section->is_active ? 'badge-light-success' : 'badge-light-warning' }}">
                                        {{ $section->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $section->sort_order }}</td>
                                <td>{{ $section->updated_at?->format('d M Y, h:i A') }}</td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'Edit', 'url' => route('administrator.home-sections.edit', $section), 'icon' => 'ki-notepad-edit'],
                                        ['label' => 'Manage products', 'url' => route('administrator.home-sections.products', $section), 'icon' => 'ki-basket'],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Move up',
                                            'url' => route('administrator.home-sections.move-up', $section),
                                            'method' => 'PATCH',
                                            'icon' => 'ki-arrow-up',
                                        ],
                                        [
                                            'label' => 'Move down',
                                            'url' => route('administrator.home-sections.move-down', $section),
                                            'method' => 'PATCH',
                                            'icon' => 'ki-arrow-down',
                                        ],
                                        [
                                            'label' => $section->is_active ? 'Deactivate' : 'Activate',
                                            'url' => route('administrator.home-sections.toggle', $section),
                                            'method' => 'PATCH',
                                            'icon' => $section->is_active ? 'ki-cross-circle' : 'ki-check-circle',
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.home-sections.destroy', $section),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'confirm' => 'Archive this homepage section? Products themselves will not be deleted.',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">No homepage sections yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $sections->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
