@extends('administrator.layouts.default')

@section('page-title', 'User Details')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Users', 'url' => route('administrator.users.index')],
        ['label' => 'Details'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Back', 'url' => route('administrator.users.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'Edit User', 'url' => route('administrator.users.edit', $managedUser), 'variant' => 'success', 'icon' => 'ki-notepad-edit'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10">
        <div class="col-xl-8">
            <div class="card card-flush internal-card h-100">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Profile</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="row g-6">
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Name</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Role</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->managementRoleLabel() }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Email</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->email }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Mobile number</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->phone ?: 'Not provided' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Status</div>
                            <div>
                                <span class="badge {{ $managedUser->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                                    {{ $managedUser->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted fs-7 mb-1">Created</div>
                            <div class="fw-bold text-gray-900">{{ $managedUser->created_at?->format('d M Y, h:i A') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card card-flush internal-card mb-5">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Activity</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-5">
                        <div class="text-muted fs-7 mb-1">Last activity</div>
                        <div class="fw-bold text-gray-900">{{ $managedUser->last_login_at?->format('d M Y, h:i A') ?? 'No recorded login yet' }}</div>
                    </div>
                    <div>
                        <div class="text-muted fs-7 mb-1">Relative time</div>
                        <div class="text-gray-700">{{ $managedUser->last_login_at?->diffForHumans() ?? 'Activity tracking will appear after the first login.' }}</div>
                    </div>
                </div>
            </div>

            <div class="card card-flush internal-card">
                <div class="card-header pt-7">
                    <div class="card-title">
                        <h3 class="fw-bold text-gray-900">Customer Order History</h3>
                    </div>
                </div>
                <div class="card-body pt-5">
                    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-6">
                        <i class="ki-outline ki-information fs-2tx text-warning me-4"></i>
                        <div class="d-flex flex-column">
                            <h4 class="text-gray-900 fw-bold mb-1">Pending order module</h4>
                            <span class="fs-6 text-gray-700">Customer order history will be connected here after the Orders phase is implemented.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
