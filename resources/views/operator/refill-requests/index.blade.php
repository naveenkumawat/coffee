@extends('operator.layouts.default')

@section('page-title', 'Refill Requests')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Operator Panel', 'url' => route('operator.dashboard')],
        ['label' => 'Refill Requests'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Inventory', 'url' => route('operator.inventory.index'), 'variant' => 'dark', 'icon' => 'ki-left'],
        ['label' => 'New Request', 'url' => route('operator.refill-requests.create'), 'variant' => 'success', 'icon' => 'ki-plus'],
    ]" />
@endsection

@section('content')
    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('operator.refill-requests.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-5 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Ingredient or request note" />
                </div>
                <div class="col-xl-4 col-md-3">
                    <label for="ingredient_id" class="form-label">Ingredient</label>
                    <select id="ingredient_id" name="ingredient_id" class="form-select">
                        <option value="">All ingredients</option>
                        @foreach ($ingredientOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('ingredient_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-12">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('operator.refill-requests.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                                        <span class="text-gray-500 fs-7">{{ $request->ingredient?->current_stock }} {{ $request->ingredient?->base_measurement_unit?->value }} currently available</span>
                                    </div>
                                </td>
                                <td>{{ number_format((float) $request->quantity, 3) }} {{ $request->measurement_unit->value }}</td>
                                <td><x-internal.refill-request-status-badge :status="$request->status" /></td>
                                <td>{{ $request->reviewedBy?->name ?: 'Awaiting review' }}</td>
                                <td>{{ $request->created_at?->format('d M Y, h:i A') }}</td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('operator.refill-requests.show', $request), 'icon' => 'ki-eye'],
                                        [
                                            'label' => 'New Request for Ingredient',
                                            'url' => route('operator.refill-requests.create', ['ingredient_id' => $request->ingredient_id]),
                                            'icon' => 'ki-plus-circle',
                                            'disabled' => $request->status->isActive(),
                                        ],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">No refill requests submitted yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $requests->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
