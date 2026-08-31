@extends('barista.layouts.default')

@section('page-title', 'Orders')

@section('page-description', 'Operational queue for accepted, preparing, ready, and completed orders.')

@section('breadcrumbs')
    <x-internal.breadcrumbs :items="[
        ['label' => 'Barista Panel', 'url' => route('barista.dashboard')],
        ['label' => 'Orders'],
    ]" />
@endsection

@section('toolbar-actions')
    <x-internal.button-group :items="[
        ['label' => 'Recipes', 'url' => route('barista.recipes.index'), 'variant' => 'dark', 'icon' => 'ki-book'],
    ]" />
@endsection

@section('content')
    <div class="row g-5 g-xl-10 mb-7">
        <div class="col-md-3">
            <x-internal.stat-card label="Payment Confirmed" :value="$statusCounts['payment_confirmed'] ?? 0" icon="ki-wallet" color="primary" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Accepted" :value="$statusCounts['accepted'] ?? 0" icon="ki-check-circle" color="info" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Preparing" :value="$statusCounts['preparing'] ?? 0" icon="ki-chef" color="dark" />
        </div>
        <div class="col-md-3">
            <x-internal.stat-card label="Ready" :value="$statusCounts['ready_for_pickup'] ?? 0" icon="ki-delivery-3" color="success" />
        </div>
    </div>

    <div class="card card-flush internal-card internal-filter-card mb-7">
        <div class="card-body pt-6">
            <form method="GET" action="{{ route('barista.orders.index') }}" class="row g-6 align-items-end internal-filter-form">
                <div class="col-xl-5 col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control" placeholder="Order number, product, customer" />
                </div>
                <div class="col-xl-3 col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All visible statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-4 col-md-12">
                    <x-internal.button-group :items="[
                        ['label' => 'Search', 'type' => 'submit', 'variant' => 'success', 'icon' => 'ki-magnifier'],
                        ['label' => 'Reset', 'url' => route('barista.orders.index'), 'variant' => 'dark', 'icon' => 'ki-arrows-circle'],
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
                                        @if ($order->isCashPayment())
                                            <span class="badge badge-light-success mt-1 align-self-start">
                                                @if ($order->payment_status === \App\Enums\PaymentStatus::Confirmed)
                                                    CASH RECEIVED
                                                @elseif ($order->isTakeaway())
                                                    CASH AT PICKUP
                                                @else
                                                    CASH
                                                @endif
                                            </span>
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
                                <td><x-internal.order-status-badge :status="$order->status" :order="$order" /></td>
                                <td>{{ $order->assignedBarista?->name ?: 'Unassigned' }}</td>
                                <td>{{ $order->placed_at?->format('d M Y, h:i A') }}</td>
                                <td class="text-end internal-action-cell">
                                    <x-internal.action-dropdown :items="[
                                        ['label' => 'View', 'url' => route('barista.orders.show', $order), 'icon' => 'ki-eye'],
                                    ]" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-10">No operational orders matched the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $orders->links('components.internal.pagination') }}
        </div>
    </div>
@endsection
