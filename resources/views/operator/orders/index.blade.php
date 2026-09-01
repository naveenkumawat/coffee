@extends('operator.layouts.default')

@section('page-title', 'Orders')

@section('page-description', 'Internal order list, payment visibility, and operational progress.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Operator Panel', 'url' => route('operator.dashboard')],
        ['label' => 'Orders'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-md-3">
            <x-internal.stat-card label="Pending Payment" :value="$statusCounts['pending_payment'] ?? 0" icon="ki-wallet" color="warning" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Preparing Queue" :value="($statusCounts['accepted'] ?? 0) + ($statusCounts['preparing'] ?? 0)" icon="ki-chef" color="dark" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Ready for Pickup" :value="$statusCounts['ready_for_pickup'] ?? 0" icon="ki-delivery-3" color="success" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Completed" :value="$statusCounts['completed'] ?? 0" icon="ki-check-circle" color="primary" />
        </div>
    </div>

    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('operator.orders.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-4 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Order number, customer, product, notes" />
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
                <div class="col-xl-3 col-md-3">
                    <label for="customer_id" class="form-label">Customer</label>
                    <select id="customer_id" name="customer_id" class="form-select">
                        <option value="">All customers</option>
                        @foreach ($customerOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('customer_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-6">
                    <label for="assigned_barista_id" class="form-label">Assigned Barista</label>
                    <select id="assigned_barista_id" name="assigned_barista_id" class="form-select">
                        <option value="">Any barista</option>
                        @foreach ($baristaOptions as $id => $name)
                            <option value="{{ $id }}" @selected((string) request('assigned_barista_id') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-3 col-md-12">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('operator.orders.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Date</th>
                            <th class="text-end internal-action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse ($orders as $order)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $order->order_number }}</span>
                                        <span class="text-muted">{{ $order->items->count() }} item(s)</span>
                                        @if ($order->isDineIn())
                                            <span class="badge badge-light-primary mt-1 align-self-start">DINE-IN · TABLE {{ $order->tableDisplayLabel() ?: '—' }}</span>
                                        @elseif ($order->isDelivery())
                                            <span class="badge badge-light-info mt-1 align-self-start">Delivery</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-900 fw-bold">{{ $order->customer?->name ?: 'Walk-in / internal order' }}</span>
                                        <span class="text-muted">{{ $order->customer?->email ?: 'No linked customer account' }}</span>
                                    </div>
                                </td>
                                <td>{{ $order->items->pluck('product_name')->join(', ') }}</td>
                                <td>Rs {{ number_format((float) $order->total_amount, 2) }}</td>
                                <td><x-internal.order-status-badge :status="$order->status" :order="$order" /></td>
                                <td>{{ $order->assignedBarista?->name ?: 'Unassigned' }}</td>
                                <td>{{ $order->placed_at?->format('d M Y, h:i A') }}</td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('operator.orders.show', $order), 'icon' => 'ki-eye'],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-10">No orders matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
