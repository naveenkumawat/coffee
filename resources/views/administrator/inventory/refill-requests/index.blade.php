@extends('administrator.layouts.default')

@section('page-title', 'Inventory Refill Requests')

@section('page-description', $pendingCount.' pending request(s) require review.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Administrator Panel', 'url' => route('administrator.dashboard')],
        ['label' => 'Inventory', 'url' => route('administrator.inventory.index')],
        ['label' => 'Refill Requests'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Inventory', 'url' => route('administrator.inventory.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('administrator.inventory.refill-requests.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-4 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Ingredient, requester, or notes" />
                </div>
                <div class="col-xl-3 col-md-3">
                    <label for="ingredient_id" class="form-label">Ingredient</label>
                    <select id="ingredient_id" name="ingredient_id" class="form-select">
                        <option value="">All ingredients</option>
                        @foreach ($ingredientOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('ingredient_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-6">
                    <label for="requested_by" class="form-label">Requested By</label>
                    <select id="requested_by" name="requested_by" class="form-select">
                        <option value="">All baristas</option>
                        @foreach ($requesterOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('requested_by') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-12">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('administrator.inventory.refill-requests.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>Ingredient</th>
                            <th>Requested</th>
                            <th>Requested By</th>
                            <th>Status</th>
                            <th>Reviewed</th>
                            <th>Date</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($requests as $request)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $request->ingredient?->name }}</span>
                                        <span class="text-muted">{{ $request->ingredient?->brand?->name ?: 'No brand assigned' }}</span>
                                        <span class="text-gray-500 fs-7">{{ $request->ingredient?->category?->name }}</span>
                                    </div>
                                </td>
                                <td>{{ number_format((float) $request->quantity, 3) }} {{ $request->measurement_unit->value }}</td>
                                <td>{{ $request->requestedBy?->name }}</td>
                                <td><x-internal.refill-request-status-badge :status="$request->status" /></td>
                                <td>{{ $request->reviewedBy?->name ?: 'Awaiting review' }}</td>
                                <td>{{ $request->created_at?->format('d M Y, h:i A') }}</td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('administrator.inventory.refill-requests.show', $request), 'icon' => 'ki-eye'],
                                        ['label' => 'Stock History', 'url' => route('administrator.inventory.history', ['ingredient_id' => $request->ingredient_id]), 'icon' => 'ki-time'],
                                        ['type' => 'separator'],
                                        [
                                            'label' => 'Approve',
                                            'url' => route('administrator.inventory.refill-requests.approve', $request),
                                            'method' => 'PATCH',
                                            'icon' => 'ki-check-circle',
                                            'disabled' => $request->status !== \App\Enums\InventoryRefillRequestStatus::Pending,
                                            'confirm' => 'Approve this refill request?',
                                        ],
                                        [
                                            'label' => 'Reject',
                                            'url' => route('administrator.inventory.refill-requests.reject', $request),
                                            'method' => 'PATCH',
                                            'icon' => 'ki-cross-circle',
                                            'danger' => true,
                                            'disabled' => $request->status !== \App\Enums\InventoryRefillRequestStatus::Pending,
                                            'confirm' => 'Reject this refill request?',
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-10">No refill requests matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $requests->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
