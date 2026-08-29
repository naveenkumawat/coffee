@extends('administrator.layouts.default')

@section('page-title', 'Users')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Users'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        [
            'label' => 'New User',
            'url' => route('administrator.users.create'),
            'variant' => 'success',
            'icon' => 'ki-plus',
        ],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.users.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-5 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ request('search') }}"
                        class="form-control"
                        placeholder="Name, email, or mobile number"
                    />
                </div>
                <div class="col-xl-3 col-md-3">
                    <label for="role" class="form-label">Role</label>
                    <select id="role" name="role" class="form-select">
                        <option value="">All roles</option>
                        @foreach ($filterRoleOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-12">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.users.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last Activity</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($users as $managedUser)
                            @php
                                $cannotArchiveSelf = auth('admin')->id() === $managedUser->id;
                                $cannotArchiveLastAdministrator = $managedUser->is_active && $managedUser->isAdministratorRole() && $activeAdministratorCount <= 1;
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $managedUser->name }}</span>
                                        <span class="text-muted">{{ $managedUser->email }}</span>
                                        <span class="text-gray-500 fs-7">{{ $managedUser->phone ?: 'No mobile number' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light-info">{{ $managedUser->managementRoleLabel() }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $managedUser->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                        {{ $managedUser->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $managedUser->created_at?->format('d M Y') }}</td>
                                <td>{{ $managedUser->last_login_at?->diffForHumans() ?? 'No recorded activity' }}</td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        [
                                            'label' => 'View',
                                            'url' => route('administrator.users.show', $managedUser),
                                            'icon' => 'ki-eye',
                                        ],
                                        [
                                            'label' => 'Edit',
                                            'url' => route('administrator.users.edit', $managedUser),
                                            'icon' => 'ki-notepad-edit',
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => $managedUser->is_active ? 'Active' : 'Inactive',
                                            'icon' => $managedUser->is_active ? 'ki-check-circle' : 'ki-cross-circle',
                                            'disabled' => true,
                                        ],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Archive',
                                            'url' => route('administrator.users.destroy', $managedUser),
                                            'method' => 'DELETE',
                                            'icon' => 'ki-trash',
                                            'danger' => true,
                                            'disabled' => $cannotArchiveSelf || $cannotArchiveLastAdministrator,
                                            'confirm' => 'Archive this user account?',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">No users matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $users->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
